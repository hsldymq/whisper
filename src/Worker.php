<?php

namespace Archman\Whisper;

use Archman\Whisper\Exception\InvalidSocketException;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;

abstract class Worker implements HandlerInterface
{
    /** @var Communicator */
    private $communicator;

    /** @var LoopInterface */
    protected $loop;

    abstract public function run(): int;

    final function __construct($socketFD)
    {
        if (!is_resource($socketFD)) {
            throw new InvalidSocketException();
        }

        $this->loop = Factory::create();
        $stream = new DuplexResourceStream($socketFD, $this->loop);
        $this->communicator = new Communicator($stream, $this);

        $this->init();

        return $this;
    }

    function __destruct()
    {
        unset($this->communicator);
        unset($this->loop);
    }

    final protected function sendMessage(Message $msg)
    {
        if (!$this->communicator->isWritable()) {
            // TODO throw exception
            $this->handleError(new \Exception());
            return;
        }

        $this->communicator->send($msg);
    }

    /**
     * 继承这个方法做一些初始化操作
     */
    protected function init() {}
}