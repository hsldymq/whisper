<?php

namespace Archman\Whisper\Interfaces;

interface ErrorHandlerInterface
{
    public function onError(\Throwable $e);
}