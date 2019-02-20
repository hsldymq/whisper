<?php

namespace Archman\Whisper\Traits;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * @property LoopInterface $loop
 */
trait TimerTrait
{
    public function addTimer(float $interval, bool $periodic, callable $handler): TimerInterface
    {
        if ($periodic) {
            $timer = $this->loop->addPeriodicTimer($interval, $handler);
        } else {
            $timer = $this->loop->addTimer($interval, $handler);
        }

        return $timer;
    }

    public function removeTimer(TimerInterface $timer)
    {
        $this->loop->cancelTimer($timer);
    }
}