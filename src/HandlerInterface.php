<?php

namespace Archman\Whisper;

interface HandlerInterface
{
    public function handleError(\Throwable $e);

    public function handleMessage(Message $msg);
}