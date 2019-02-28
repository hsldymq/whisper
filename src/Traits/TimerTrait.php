<?php

namespace Archman\Whisper\Traits;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * @method LoopInterface getEventLoop
 */
trait TimerTrait
{
    private $timers = [];

    public function addTimer(float $interval, bool $periodic, callable $handler): TimerInterface
    {
        if ($periodic) {
            $timer = $this->getEventLoop()->addPeriodicTimer($interval, $handler);
        } else {
            $timer = $this->getEventLoop()->addTimer($interval, $handler);
        }

        $this->timers[] = $timer;

        return $timer;
    }

    public function removeTimer(TimerInterface $timer)
    {
        $this->getEventLoop()->cancelTimer($timer);

        foreach ($this->timers as $idx => $each) {
            if ($each === $timer) {
                unset($this->timers[$idx]);
            }
        }
    }

    public function removeAllTimers()
    {
        foreach ($this->timers as $idx => $each) {
            $this->getEventLoop()->cancelTimer($each);
            unset($this->timers[$idx]);
        }
    }
}