<?php

declare(strict_types=1);

namespace Archman\Whisper;

use Archman\Whisper\Exception\CreateSocketException;
use Archman\Whisper\Exception\ForkException;
use Archman\Whisper\Exception\UnwritableSocketException;
use Archman\Whisper\Exception\WorkerNotExistException;
use Archman\Whisper\Interfaces\MessageHandler;
use Archman\Whisper\Interfaces\WorkerFactoryInterface;
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
 * @event __workerExit,         参数: string $workerID, int $pid
 * @event __sendingMessage      参数: Message $message
 *
 * 要捕捉和发布事件,使用:
 *      $this->on和$this->emit方法
 */
abstract class AbstractMaster extends EventEmitter
{
    use SignalTrait;
    use TimerTrait;
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

    /**
     * @var array
     * [
     *      $pid => $workerID,
     *      ...
     * ]
     */
    private $workerIDs = [];

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

        $this->addSignalHandler(SIGCHLD, function () {
            $pid = pcntl_wait($status, WNOHANG);
            if ($pid > 0) {
                /** @var string|null $workerID */
                $workerID = $this->workerIDs[$pid] ?? null;
                if ($workerID !== null) {
                    $this->removeWorker($workerID);
                    $this->emit('__workerExit', [$workerID, $pid]);
                }
            }
        });
    }

    /**
     * @return array
     */
    public function getWorkerIDs(): array
    {
        return array_keys($this->workers);
    }

    /**
     * @param string $workerID
     * 
     * @return int|null
     */
    protected function getWorkerPID(string $workerID)
    {
        return $this->workers[$workerID]['pid'] ?? null;
    }

    /**
     * @return int
     */
    public function countWorkers(): int
    {
        return count($this->workers);
    }

    /**
     * 开始阻塞处理消息传输和处理,直至指定时间返回.
     *
     * @param float $interval 阻塞时间(秒). 不传代表永久阻塞.
     * @example $master->run(0.1);  // 阻塞100毫秒后返回.
     * @example $master->run(2);    // 阻塞2秒后返回.
     */
    protected function process(float $interval = null)
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
    protected function stopProcess()
    {
        $this->eventLoop->stop();
        if ($this->processTimer) {
            $this->eventLoop->cancelTimer($this->processTimer);
            $this->processTimer = null;
        }
    }

    /**
     * @throws
     */
    protected function daemonize()
    {
        $pid = pcntl_fork();
        if ($pid > 0) {
            exit(0);
        } else if ($pid < 0) {
            throw new ForkException("daemonize failed", ForkException::DAEMONIZING);
        }

        posix_setsid();
        // 确保不会成为session组长
        $pid = pcntl_fork();
        if ($pid > 0) {
            exit(0);
        } else if ($pid < 0) {
            throw new ForkException("daemonize failed", ForkException::DAEMONIZING);
        }
        umask(0);
    }

    /**
     * @param string $workerID
     * 
     * @return Communicator|null
     */
    protected function getCommunicator(string $workerID)
    {
        return $this->workers[$workerID]['communicator'] ?? null;
    }

    /**
     * @return LoopInterface
     */
    protected function getEventLoop(): LoopInterface
    {
        return $this->eventLoop;
    }

    /**
     * @param string $workerID
     * 
     * @return bool
     */
    protected function workerExists(string $workerID): bool
    {
        return isset($this->workers[$workerID]);
    }

    /**
     * @param string $workerID
     * 
     * @return bool
     */
    protected function isWorkerDisconnected(string $workerID): bool
    {
        $c = $this->getCommunicator($workerID);

        if ($c) {
            return !$c->isReadable() && !$c->isWritable();
        }

        return true;
    }

    /**
     * 移除保存的worker信息.
     *
     * @param string $workerID
     *
     * @return bool
     */
    protected function removeWorker(string $workerID): bool
    {
        if (!isset($this->workers[$workerID])) {
            return false;
        }
        $pid = $this->workers[$workerID]['pid'];
        unset($this->workerIDs[$pid]);
        unset($this->workers[$workerID]);

        return true;
    }

    /**
     * Send a signal to a worker.
     * 
     * @param string $workerID
     * @param int $signal
     * @param bool $remove 是否同时移除worker信息
     * 
     * @return bool
     */
    protected function killWorker(string $workerID, int $signal, bool $remove): bool
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
     * Override this method to provide a new worker id generator.
     *
     * @return string
     */
    protected function makeWorkerID(): string
    {
        return Helper::uuid();
    }

    /**
     * 将消息写入缓冲区.
     * 
     * @param string $workerID
     * @param Message $msg
     * 
     * @return bool
     * @throws
     */
    final protected function sendMessage(string $workerID, Message $msg): bool
    {
        if (!$this->workerExists($workerID)) {
            throw new WorkerNotExistException($workerID);
        }

        $communicator = $this->getCommunicator($workerID);
        if (!$communicator->isWritable()) {
            throw new UnwritableSocketException();
        }

        $this->emit('__sendingMessage', [$msg]);

        return $communicator->send($msg);
    }

    /**
     * Fork a new worker.
     *
     * @param WorkerFactoryInterface $factory
     * @param callable $afterCreated 当worker被创建,被执行于worker进程中,你可以用它清理从父进程fork过来的无用数据或者做其他操作.
     * 
     * @return string|null
     * @throws
     */
    final protected function createWorker(WorkerFactoryInterface $factory, callable $afterCreated = null)
    {
        $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($socketPair === false) {
            throw new CreateSocketException();
        }

        $workerID = $this->makeWorkerID();
        $pid = pcntl_fork();
        if ($pid > 0) {
            // parent
            fclose($socketPair[1]);
            unset($socketPair[1]);

            $stream = new DuplexResourceStream($socketPair[0], $this->eventLoop);
            $communicator = new Communicator($stream, $this->newHandler($workerID));

            $this->workerIDs[$pid] = $workerID;
            $this->workers[$workerID] = [
                'pid' => $pid,
                'communicator' => $communicator,
            ];

            $onClose = (function (string $workerID) {
                return function () use ($workerID) {
                    $pid = $this->workers[$workerID]['pid'] ?? null;
                    if ($pid !== null) {
                        $this->removeWorker($workerID);
                        $this->emit("__workerExit", [$workerID, $pid]);
                    }
                };
            })($workerID);
            $stream->on("close", $onClose);

            if ($this->isWorkerDisconnected($workerID)) {
                // 子进程如果在初始化时出错,这里希望能检测出来
                $this->removeWorker($workerID);
                throw new ForkException("worker exit", ForkException::CHILD_EXIT);
            }
        } else if ($pid === 0) {
            // child
            fclose($socketPair[0]);
            $this->removeAllListeners();
            $this->removeAllSignalHandlers();
            $this->removeAllTimers();
            $this->unregisterAllShutdown();
            if (is_callable($afterCreated)) {
                $afterCreated();
            }
            unset($socketPair[0], $this->eventLoop, $this->workers);

            $worker = $factory->makeWorker($workerID, $socketPair[1]);
            $worker->run();
            exit(0);
        } else {
            throw new ForkException("fork failed", ForkException::CREATING);
        }

        return $workerID;
    }

    private function newHandler(string $workerID): MessageHandler
    {
        return new class($this, $workerID) implements MessageHandler {
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
        };
    }
}
