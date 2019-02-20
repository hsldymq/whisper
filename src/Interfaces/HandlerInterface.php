<?php

namespace Archman\Whisper\Interfaces;

use Archman\Whisper\Message;

interface HandlerInterface
{
    public function handleError(\Throwable $e);

    public function handleMessage(Message $msg);
}