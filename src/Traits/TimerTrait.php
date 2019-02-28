<?php

namespace Archman\Whisper\Traits;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * @property LoopInterface $eventLoop
 */
trait TimerTrait
{
    private $timers = [];

    public function addTimer(float $interval, bool $periodic, callable $handler): TimerInterface
    {
        if ($periodic) {
            $timer = $this->eventLoop->addPeriodicTimer($interval, $handler);
        } else {
            $timer = $this->eventLoop->addTimer($interval, $handler);
        }

        $this->timers[] = $timer;

        return $timer;
    }

    public function removeTimer(TimerInterface $timer)
    {
        $this->eventLoop->cancelTimer($timer);

        foreach ($this->timers as $idx => $each) {
            if ($each === $timer) {
                unset($this->timers[$idx]);
            }
        }
    }

    public function removeAllTimers()
    {
        foreach ($this->timers as $idx => $each) {
            $this->eventLoop->cancelTimer($each);
            unset($this->timers[$idx]);
        }
    }
}