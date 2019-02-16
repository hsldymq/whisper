<?php

namespace Archman\Whisper;

use React\EventLoop\Factory;

abstract class Worker
{
    /** @var Communicator */
    private $communicator;

    private $event;

    abstract public function run(): int;

    public function init(Communicator $c): self
    {
        $this->event = Factory::create();
        $this->communicator = $c;
    }

    final protected function sendMessage(Message $msg)
    {
        if (!$this->communicator->isWritable()) {
            // TODO throw exception
            throw new \Exception();
        }

        $this->communicator->send($msg);
    }

}