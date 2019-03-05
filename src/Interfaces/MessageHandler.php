<?php

declare(strict_types=1);

namespace Archman\Whisper\Interfaces;

use Archman\Whisper\Message;

interface MessageHandler
{
    public function handleMessage(Message $msg);
}
