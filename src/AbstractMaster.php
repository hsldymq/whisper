<?php

namespace Archman\Whisper;

use Archman\Whisper\Exception\CreateSocketException;
use Archman\Whisper\Exception\ForkException;
use Archman\Whisper\Exception\UnwritableSocketException;
use Archman\Whisper\Exception\WorkerNotExistException;
use Archman\Whisper\Interfaces\ErrorHandlerInterface;
use Archman\Whisper\Interfaces\HandlerInterface;
use Archman\Whisper\Interfaces\WorkerFactoryInterface;
use Archman\Whisper\Traits\ErrorTrait;
use Archman\Whisper\Traits\SignalTrait;
use Archman\Whisper\Traits\TerminateTrait;
use Archman\Whisper\Traits\TimerTrait;
use Evenement\EventEmitter;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Stream\DuplexResourceStream;

/**
 * 预定义的event:
 *      workerExit
 * 要捕捉和使用事件,使用:
 *      $this->on和$this->emit方法
 */
abstract class AbstractMaster extends EventEmitter
{
    use SignalTrait;
    use TimerTrait;
    use ErrorTrait;
    use TerminateTrait;

    /**
     * @var array 数据结构
     *  [
     *      $workerID => [
     *          'pid' => xxx,
     *          'communicator' => \Archman\Whisper\Communicator,
     *      ],
     *      ...
     *  ]
     */
    private $workers = [];

    /** @var LoopInterface */
    private $eventLoop;

    /** @var TimerInterface */
    private $processTimer = null;

    /**
     * @example
     *  public function run()
     *  {
     *      $this->process();
     *  }
     *
     * @example
     *  public function run()
     *  {
     *      while (true) {
     *          // do something
     *          // etc. $this->sendMessage(new Message(0, "content"));
     *          $this->process(2);      // block for 2 seconds.
     *      }
     *  }
     */
    abstract public function run();

    /**
     * 该方法处理由worker发来的消息.
     *
     * @param string $workerID
     * @param Message $msg
     *
     * @return mixed
     */
    abstract public function onMessage(string $workerID, Message $msg);

    /**
     * 子类重载构造函数要确保基类构造函数被调用.
     */
    public function __construct()
    {
        $this->eventLoop = Factory::create();
    }

    /**
     * 开始阻塞处理消息传输和处理,直至指定时间返回.
     *
     * @param float $interval 阻塞时间(秒). 不传代表永久阻塞.
     * @example $master->run(0.1);  // 阻塞100毫秒后返回.
     * @example $master->run(2);    // 阻塞2秒后返回.
     */
    public function process(float $interval = null)
    {
        if ($interval !== null) {
            $this->processTimer = $this->eventLoop->addTimer($interval, function () {
                $this->eventLoop->stop();
                $this->processTimer = null;
            });
        }
        $this->eventLoop->run();
    }

    /**
     * 停止阻塞处理.
     */
    public function stopProcess()
    {
        $this->eventLoop->stop();
        if ($this->processTimer) {
            $this->eventLoop->cancelTimer($this->processTimer);
            $this->processTimer = null;
        }
    }

    public function daemonize(): bool
    {
        $pid = pcntl_fork();
        if ($pid > 0) {
            exit(0);
        } else if ($pid < 0) {
            $this->raiseError(new ForkException("", ForkException::DAEMONIZING));
            return false;
        }

        posix_setsid();
        // 确保不会成为session组长
        $pid = pcntl_fork();
        if ($pid > 0) {
            exit(0);
        } else if ($pid < 0) {
            $this->raiseError(new ForkException("", ForkException::DAEMONIZING));
            return false;
        }
        umask(0);

        return true;
    }

    public function getWorkerIDs(): array
    {
        return array_keys($this->workers);
    }

    /**
     * @param string $workerID
     * @return int|null
     */
    protected function getWorkerPID(string $workerID)
    {
        return $this->workers[$workerID]['pid'] ?? null;
    }

    /**
     * @param string $workerID
     * @return Communicator|null
     */
    protected function getCommunicator(string $workerID)
    {
        return $this->workers[$workerID]['communicator'] ?? null;
    }

    protected function getEventLoop(): LoopInterface
    {
        return $this->eventLoop;
    }

    protected function countWorkers(): int
    {
        return count($this->workers);
    }

    protected function isWorkerExists(string $workerID): bool
    {
        return isset($this->workers[$workerID]);
    }

    protected function isWorkerDisconnected(string $workerID): bool
    {
        $c = $this->getCommunicator($workerID);

        if ($c) {
            return !$c->isReadable() && !$c->isWritable();
        }

        return true;
    }

    /**
     * @param string $workerID
     *
     * @return bool 不存在指定worker id会返回false
     */
    protected function removeWorker(string $workerID): bool
    {
        if (isset($this->workers[$workerID])) {
            unset($this->workers[$workerID]);

            return true;
        }

        return false;
    }

    protected function killWorker(string $workerID, int $signal, bool $remove = true): bool
    {
        $pid = $this->getWorkerPID($workerID);
        if (!$pid || !posix_kill($pid, $signal)) {
            return false;
        }

        if ($remove) {
            $this->removeWorker($workerID);
        }

        return true;
    }

    /**
     * 将消息写入缓冲区.
     * @param string $workerID
     * @param Message $msg
     * @return bool
     */
    final protected function sendMessage(string $workerID, Message $msg): bool
    {
        if (!$this->isWorkerExists($workerID)) {
            $this->raiseError(new WorkerNotExistException());
            return false;
        }

        $communicator = $this->getCommunicator($workerID);
        if (!$communicator->isWritable()) {
            $this->raiseError(new UnwritableSocketException());
            return false;
        }

        $this->emit('onSendingMessage', [$msg]);

        return $communicator->send($msg);
    }

    /**
     * @param WorkerFactoryInterface $factory
     * @return string|null
     */
    final protected function createWorker(WorkerFactoryInterface $factory)
    {
        $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($socketPair === false) {
            $this->raiseError(new CreateSocketException());
            return null;
        }

        $workerID = Helper::uuid();
        $pid = pcntl_fork();
        if ($pid > 0) {
            // parent
            fclose($socketPair[1]);
            unset($socketPair[1]);

            $stream = new DuplexResourceStream($socketPair[0], $this->eventLoop);
            $communicator = new Communicator($stream, $this->newHandler($workerID));

            $this->workers[$workerID] = [
                'pid' => $pid,
                'communicator' => $communicator,
            ];
            $onClose = (function (string $workerID) {
                return function () use ($workerID) {
                    if ($this->removeWorker($workerID)) {
                        $this->emit("workerExit", [$workerID]);
                    }
                };
            })($workerID);
            $stream->on("close", $onClose);

            if ($this->isWorkerDisconnected($workerID)) {
                // 子进程有可能在初始化时出错,这里做一次检测
                $stream->emit('close');
                return null;
            }
        } else if ($pid === 0) {
            // child
            fclose($socketPair[0]);
            $this->removeAllListeners();
            $this->removeAllSignalHandlers();
            $this->removeAllTimers();
            $this->unregisterAllShutdown();
            unset($socketPair[0], $this->eventLoop, $this->workers);

            $worker = $factory->makeWorker($workerID, $socketPair[1]);
            $worker->run();
            exit(0);
        } else {
            $this->raiseError(new ForkException("", ForkException::CREATING));
            return null;
        }

        return $workerID;
    }

    private function newHandler(string $workerID): HandlerInterface
    {
        return new class($this, $workerID) implements HandlerInterface {
            /** @var AbstractMaster */
            private $master;

            /** @var string */
            private $workerID;

            public function __construct(AbstractMaster $master, string $workerID)
            {
                $this->master = $master;
                $this->workerID = $workerID;
            }

            public function __destruct()
            {
                unset($this->master);
            }

            public function handleMessage(Message $msg)
            {
                $this->master->onMessage($this->workerID, $msg);
            }

            public function handleError(\Throwable $e)
            {
                if ($this->master instanceof ErrorHandlerInterface) {
                    $this->master->onError($e);
                    return;
                }

                throw $e;
            }
        };
    }
}