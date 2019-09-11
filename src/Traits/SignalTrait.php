<?php

declare(strict_types=1);

namespace Archman\Whisper\Traits;

use React\EventLoop\LoopInterface;

/**
 * @method LoopInterface getEventLoop
 */
trait SignalTrait
{
    /**
     * @var array [
     *      [
     *          'original' => (callable)
     *          'wrapped' => (callable)
     *      ],
     *      ...
     * ]
     */
    private $signalHandlers = [];

    /**
     * 添加信号处理器.
     *
     * @param int $sig
     * @param callable $handler
     */
    public function addSignalHandler(int $sig, callable $handler)
    {
        $wrapped = (function (callable $handler) {
            return function (int $signal) use ($handler) {
                $handler($signal, $this);
            };
        })($handler);

        $this->getEventLoop()->addSignal($sig, $wrapped);

        $this->signalHandlers[$sig][] = [
            'original' => $handler,
            'wrapped' => $wrapped,
        ];
    }

    /**
     * 移除信号处理器.
     *
     * @param int $sig
     * @param callable|null $handler 如果不传则移除该信号下的所有处理器
     */
    public function removeSignalHandler(int $sig, callable $handler = null)
    {
        foreach (($this->signalHandlers[$sig] ?? []) as $idx => $h) {
            if ($handler === null || $handler === $h['original']) {
                $this->getEventLoop()->removeSignal($sig, $h['wrapped']);
                unset($this->signalHandlers[$sig][$idx]);
            }
        }
    }

    public function removeAllSignalHandlers()
    {
        foreach ($this->signalHandlers as $sig => $handlers) {
            foreach ($handlers as $idx => $h) {
                $this->getEventLoop()->removeSignal($sig, $h['wrapped']);
                unset($this->signalHandlers[$sig][$idx]);
            }
        }
    }
}
