<?php

namespace Archman\Whisper;

class Message
{
    private $status;

    private $content;

    public function __construct(int $status, string $content)
    {
        $this->status = $status;

        $this->content = $content;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentLength(): int
    {
        return strlen($this->content);
    }
}