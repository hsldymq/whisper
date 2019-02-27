<?php

namespace Archman\Whisper;

class Message
{
    private $type;

    private $content;

    /**
     * @param int $type 确保取值在[0, 255]这个区间,否则在序列化时会被截断
     * @param string $content 确保文本长度在[0, 0xFFFFFF]这个区间,否则同上
     */
    public function __construct(int $type, string $content)
    {
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