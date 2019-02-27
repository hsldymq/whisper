<?php

namespace Archman\Whisper\Traits;

use React\EventLoop\LoopInterface;

/**
 * @property LoopInterface $loop
 */
trait SignalTrait
{
    private $signalHandlers = [];

    public function addSignalHandler(int $sig, callable $handler)
    {
        $this->loop->addSignal($sig, $handler);

        $this->signalHandlers[$sig][] = $handler;
    }

    public function removeSignalHandler(int $sig, callable $handler)
    {
        $this->loop->removeSignal($sig, $handler);

        foreach (($this->signalHandlers[$sig] ?? []) as $idx => $h) {
            if ($h === $handler) {
                unset($this->signalHandlers[$sig][$idx]);
            }
        }
    }

    public function removeAllSignalHandlers()
    {
        foreach ($this->signalHandlers as $sig => $handlers) {
            foreach ($handlers as $idx => $eachHandler) {
                $this->loop->removeSignal($sig, $eachHandler);
                unset($this->signalHandlers[$sig][$idx]);
            }
        }
    }
}