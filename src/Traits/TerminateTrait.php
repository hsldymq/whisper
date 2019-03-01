<?php

namespace Archman\Whisper\Traits;

trait TerminateTrait
{
    private $registered = false;

    private $shutdownCallbacks = [];

    public function registerShutdown(callable $callback, ...$params)
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
    }

    public function unregisterShutdown(callable $callback)
    {
        foreach ($this->shutdownCallbacks as $idx => $each) {
            if ($each['callback'] === $callback) {
                unset($this->shutdownCallbacks[$idx]);
                break;
            }
        }
    }

    public function unregisterAllShutdown()
    {
        foreach ($this->shutdownCallbacks as $idx => $each) {
            unset($this->shutdownCallbacks[$idx]);
        }
    }
}