<?php

namespace Archman\Whisper\Traits;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * @property LoopInterface $loop
 */
trait TimerTrait
{
    private $timers = [];

    public function addTimer(float $interval, bool $periodic, callable $handler): TimerInterface
    {
        if ($periodic) {
            $timer = $this->loop->addPeriodicTimer($interval, $handler);
        } else {
            $timer = $this->loop->addTimer($interval, $handler);
        }

        $this->timers[] = $timer;

        return $timer;
    }

    public function removeTimer(TimerInterface $timer)
    {
        $this->loop->cancelTimer($timer);

        foreach ($this->timers as $idx => $each) {
            if ($each === $timer) {
                unset($this->timers[$idx]);
            }
        }
    }

    public function removeAllTimers()
    {
        foreach ($this->timers as $idx => $each) {
            $this->loop->cancelTimer($each);
            unset($this->timers[$idx]);
        }
    }
}