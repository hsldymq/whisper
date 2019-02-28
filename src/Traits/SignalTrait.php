<?php

namespace Archman\Whisper\Traits;

use React\EventLoop\LoopInterface;

/**
 * @property LoopInterface $eventLoop
 */
trait SignalTrait
{
    private $signalHandlers = [];

    public function addSignalHandler(int $sig, callable $handler)
    {
        $this->eventLoop->addSignal($sig, $handler);

        $this->signalHandlers[$sig][] = $handler;
    }

    public function removeSignalHandler(int $sig, callable $handler)
    {
        $this->eventLoop->removeSignal($sig, $handler);

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
                $this->eventLoop->removeSignal($sig, $eachHandler);
                unset($this->signalHandlers[$sig][$idx]);
            }
        }
    }
}