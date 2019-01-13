<?php

namespace Archman\Whisper;

class Message
{
    private $status;

    private $message;

    public function __construct(int $status, string $message)
    {
        $this->status = $status;

        $this->message = $message;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessageLength(): int
    {
        return strlen($this->message);
    }
}