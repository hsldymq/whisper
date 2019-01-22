<?php

namespace Archman\Whisper;

class Message
{
    private $type;

    private $content;

    public function __construct(int $type, string $content)
    {
        if ($type > 255) {
            // TODO throw exception
        }

        if (strlen($content) > 0xFFFFFF) {
            // TODO throw exception
        }

        $this->type = $type;
        $this->content = $content;
    }

    public function getType(): int
    {
        return $this->type;
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