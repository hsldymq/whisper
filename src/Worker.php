<?php

namespace Archman\Whisper;

use React\EventLoop\Factory;

abstract class Worker
{
    private $communicator;

    private $event;

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

    abstract public function run(): int;
}