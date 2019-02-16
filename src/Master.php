<?php

namespace Archman\Whisper;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;

abstract class Master
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
    private $loop;

    private $errorHandler;

    private $messageHandler;

    private $onMessage;

    abstract public function run();

    final public function init(array $customArgs = [])
    {
        $this->loop = Factory::create();
        $this->onMessage = function (Message $msg) {
            $result = null;
            if (is_callable($this->messageHandler)) {
                $result = $this->messageHandler($msg);
            }

            return $result;
        };

        $this->customInit($customArgs);
    }

    public function registerMessageHandler(callable $handler)
    {
        $this->messageHandler = $handler;
    }

    public function registerErrorHandler(callable $handler)
    {
        $this->errorHandler = $handler;
    }

    /**
     * override this
     *
     * @param array $args
     */
    protected function customInit(array $args)
    {
    }

    final protected function sendMessage(string $workerID, Message $msg)
    {
        if (!isset($this->workers[$workerID])) {
            // TODO throw exception
            throw new \Exception();
        }

        /** @var Communicator $communicator */
        $communicator = $this->workers[$workerID]['communicator'];
        if (!$communicator->isWritable()) {
            // TODO throw exception
            throw new \Exception();
        }

        $communicator->send($msg);
    }

    final protected function fork(WorkerFactoryInterface $factory): string
    {
        $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($socketPair === false) {
            // TODO throw exception
        }

        $pid = pcntl_fork();
        if ($pid > 0) {
            // child
            fclose($socketPair[1]);
            unset($socketPair[1]);

            $stream = new DuplexResourceStream($socketPair[0], $this->loop);
            $communicator = new Communicator($stream);
            $communicator->messageHandler = $this->onMessage;

            $workerID = spl_object_hash($communicator);
            $this->workers[$workerID] = [
                'pid' => $pid,
                'communicator' => $communicator,
                'info' => [],
            ];
        } else if ($pid === 0) {
            // parent
            fclose($socketPair[0]);
            unset(
                $socketPair[0],
                $this->workers,
                $this->loop,
                $this->onMessage,
                $this->messageHandler,
                $this->errorHandler
            );

            try {
                $stream = new DuplexResourceStream($socketPair[1], $this->loop);
                $communicator = new Communicator($stream);
                $result = $factory->makeWorker()->init($communicator)->run();
            } catch (\Throwable $e) {
                $result = -1;
            }
            exit($result);
        } else {
            // TODO throw exception
        }

        return $workerID;
    }
}