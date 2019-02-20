<?php

namespace Archman\Whisper;

use Archman\Whisper\Exception\InvalidSocketException;
use Archman\Whisper\Interfaces\HandlerInterface;
use Archman\Whisper\Traits\SignalTrait;
use Archman\Whisper\Traits\TimerTrait;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;

abstract class Worker implements HandlerInterface
{
    use SignalTrait;
    use TimerTrait;

    /** @var Communicator */
    private $communicator;

    /** @var LoopInterface */
    protected $loop;

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

    public function run()
    {
        $this->loop->run();
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