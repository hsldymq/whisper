<?php

namespace Archman\Whisper;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;

abstract class Master implements HandlerInterface
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

    abstract public function run();

    final public function __construct()
    {
        $this->loop = Factory::create();

        $this->init();
    }

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

        return $communicator->send($msg);
    }

    /**
     * @param WorkerFactoryInterface $factory
     * @return string|null
     */
    final protected function fork(WorkerFactoryInterface $factory)
    {
        $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($socketPair === false) {
            // TODO throw exception
            $this->handleError(new \Exception());
            return null;
        }

        $pid = pcntl_fork();
        if ($pid > 0) {
            echo "forked child: {$pid}\n";
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
        } else if ($pid === 0) {
            // child
            fclose($socketPair[0]);
            unset(
                $socketPair[0],
                $this->workers,
                $this->loop
            );

            $worker = $factory->makeWorker($socketPair[1]);
            $result = $worker->run();
            exit($result);
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