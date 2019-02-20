<?php

namespace Archman\Whisper\Traits;

use React\EventLoop\LoopInterface;

/**
 * @property LoopInterface $loop
 */
trait SignalTrait
{
    public function addSignalHandler(int $sig, callable $handler)
    {
        $this->loop->addSignal($sig, $handler);
    }

    public function removeSignalHandler(int $sig, callable $handler)
    {
        $this->loop->removeSignal($sig, $handler);
    }
}