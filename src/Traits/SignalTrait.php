<?php

declare(strict_types=1);

namespace Archman\Whisper\Traits;

use React\EventLoop\LoopInterface;

/**
 * @method LoopInterface getEventLoop
 */
trait SignalTrait
{
    private $signalHandlers = [];

    /**
     * 添加信号处理器.
     *
     * @param int $sig
     * @param callable $handler
     */
    public function addSignalHandler(int $sig, callable $handler)
    {
        $this->getEventLoop()->addSignal($sig, $handler);

        $this->signalHandlers[$sig][] = $handler;
    }

    /**
     * 移除信号处理器.
     *
     * @param int $sig
     * @param callable|null $handler 如果不传则移除该信号下的所有处理器
     */
    public function removeSignalHandler(int $sig, callable $handler = null)
    {
        $this->getEventLoop()->removeSignal($sig, $handler);

        foreach (($this->signalHandlers[$sig] ?? []) as $idx => $h) {
            if ($handler === null || $h === $handler) {
                unset($this->signalHandlers[$sig][$idx]);
            }
        }
    }

    public function removeAllSignalHandlers()
    {
        foreach ($this->signalHandlers as $sig => $handlers) {
            foreach ($handlers as $idx => $eachHandler) {
                $this->getEventLoop()->removeSignal($sig, $eachHandler);
                unset($this->signalHandlers[$sig][$idx]);
            }
        }
    }
}
