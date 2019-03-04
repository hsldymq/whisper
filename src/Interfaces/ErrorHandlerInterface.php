<?php

declare(strict_types=1);

namespace Archman\Whisper\Interfaces;

interface ErrorHandlerInterface
{
    public function onError(\Throwable $e);
}
