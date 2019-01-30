<?php

namespace Archman\Whisper;

use React\EventLoop\Factory;

abstract class Worker
{
    private $communicator;

    private $event;

    abstract public function run(): int;

    /**
     * 需要确保父类的构造方法会被调用.
     *
     * @param Communicator
     */
    public function __construct(Communicator $c)
    {
        $this->event = Factory::create();
        $this->communicator = $c;
    }

    final protected function sendMessage(Message $msg)
    {
        if ($this->communicator->isClosed() || !$this->communicator->isWritable()) {
            // TODO throw exception
            throw new \Exception();
        }

        $this->communicator->enqueueMessage($msg);
    }

}