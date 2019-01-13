<?php

namespace Archman\Whisper;

class Message
{
    private $status;

    private $message;

    private $identifier;

    public function __construct(int $status, string $message, string $identifier = null)
    {
        $this->status = $status;

        $this->message = $message;

        $this->identifier = $this->setIdentifier($identifier ?? uuid());
    }

    public function setIdentifier(string $id): string
    {
        $this->identifier = $id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
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