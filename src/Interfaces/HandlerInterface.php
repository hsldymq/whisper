<?php

declare(strict_types=1);

namespace Archman\Whisper\Interfaces;

use Archman\Whisper\Message;

interface HandlerInterface
{
    public function handleMessage(Message $msg);

    public function handleError(\Throwable $e);
}
