<?php

namespace Archman\Whisper;

use Evenement\EventEmitter;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;

abstract class Master extends EventEmitter implements HandlerInterface
{
    /**
     * @var array 数据结构
     *  [
     *      $workerID => [
     *          'pid' => xxx,
     *          'communicator' => \Archman\Whisper\Communicator,
     *          'info' => [],       // 自定义信息
     *      ],
     *      ...
     *  ]
     */
    protected $workers = [];

    /** @var LoopInterface */
    protected $loop;

    /**
     * @example
     *  public function run()
     *  {
     *      while (true) {
     *          // do something
     *          // etc. $this->sendMessage(new Message(0, "content"));
     *          $this->process(2);      // block for 2 seconds.
     *      }
     *  }
     *
     */
    abstract public function run();

    final public function __construct()
    {
        $this->loop = Factory::create();

        $this->init();
    }

    /**
     * 开始阻塞处理消息传输和处理,直至指定时间返回.
     *
     * @param float $interval 阻塞时间,秒.
     * @example $master->run(0.1);  // 阻塞100毫秒后返回.
     * @example $master->run(2);    // 阻塞2秒后返回.
     */
    public function process(float $interval)
    {
        $this->loop->addTimer($interval, function () {
            $this->loop->stop();
        });
        $this->loop->run();
    }

    /**
     * 将消息写入缓冲区.
     * @param string $workerID
     * @param Message $msg
     * @return bool
     */
    final protected function sendMessage(string $workerID, Message $msg): bool
    {
        if (!isset($this->workers[$workerID])) {
            // TODO throw exception
            $this->handleError(new \Exception());
            return false;
        }

        /** @var Communicator $communicator */
        $communicator = $this->workers[$workerID]['communicator'];
        if (!$communicator->isWritable()) {
            // TODO throw exception
            $this->handleError(new \Exception());
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
            // TODO throw exception
            $this->handleError(new \Exception());
            return null;
        }

        $pid = pcntl_fork();
        if ($pid > 0) {
            // parent
            fclose($socketPair[1]);
            unset($socketPair[1]);

            $stream = new DuplexResourceStream($socketPair[0], $this->loop);
            $communicator = new Communicator($stream, $this);

            $workerID = spl_object_hash($communicator);
            $this->workers[$workerID] = [
                'pid' => $pid,
                'communicator' => $communicator,
                'info' => [],
            ];
            $stream->on("close", function () use ($workerID) {
                $this->emit("onWorkerExit", [$workerID]);
                unset($this->workers[$workerID]);
                echo $workerID . " Closed\n";
            });
        } else if ($pid === 0) {
            // child
            fclose($socketPair[0]);
            unset(
                $socketPair[0],
                $this->workers,
                $this->loop
            );

            $worker = $factory->makeWorker($socketPair[1]);
            $worker->run();
            exit();
        } else {
            // TODO throw exception
            $this->handleError(new \Exception());
            return null;
        }

        return $workerID;
    }

    /**
     * 继承这个方法做一些额外的初始化操作
     */
    protected function init() {}
}