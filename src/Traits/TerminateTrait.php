<?php

declare(strict_types=1);

namespace Archman\Whisper\Traits;

trait TerminateTrait
{
    private $registered = false;

    private $shutdownCallbacks = [];

    /**
     * 注册一个shutdown function.
     *
     * @param callable $callback
     * @param mixed ...$params
     *
     * @return self
     */
    public function registerShutdown(callable $callback, ...$params): self
    {
        if (!$this->registered) {
            register_shutdown_function(function () {
                foreach ($this->shutdownCallbacks as $each) {
                    $each['callback'](...$each['params']);
                }
            });

            $this->registered = true;
        }

        $this->shutdownCallbacks[] = [
            'callback' => $callback,
            'params' => $params,
        ];

        return $this;
    }

    /**
     * 注销指定shutdown function.
     *
     * @param callable $callback
     */
    public function unregisterShutdown(callable $callback)
    {
        foreach ($this->shutdownCallbacks as $idx => $each) {
            if ($each['callback'] === $callback) {
                unset($this->shutdownCallbacks[$idx]);
                break;
            }
        }
    }

    /**
     * 注销所有shutdown function.
     */
    public function unregisterAllShutdown()
    {
        foreach ($this->shutdownCallbacks as $idx => $each) {
            unset($this->shutdownCallbacks[$idx]);
        }
    }
}
