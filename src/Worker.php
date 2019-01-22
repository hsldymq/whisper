<?php

namespace Archman\Whisper;

abstract class Worker
{
    private $communicator;

    public function __construct(Communicator $c)
    {
        $this->communicator = $c;
    }

    abstract public function run(): int;
}