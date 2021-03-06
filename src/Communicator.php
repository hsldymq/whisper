<?php

declare(strict_types=1);

namespace Archman\Whisper;

use Archman\ByteOrder\ByteOrder;
use Archman\ByteOrder\Operator;
use Archman\Whisper\Exception\CheckMagicWordException;
use Archman\Whisper\Interfaces\MessageHandler;
use React\Stream\DuplexResourceStream;

class Communicator
{
    // 头部magic word,用于标志一个正确的消息开始
    const MAGIC_WORD = "\0\0arch\0\0";

    // 消息头总长度
    const HEADER_SIZE = 12;

    // 头部消息类型字段长度(字节)
    const HEADER_TYPE_SIZE = 1;

    // 头部消息体长度字段长度(字节)
    // 一个消息体最多容纳16M载荷
    const HEADER_LENGTH_SIZE = 3;

    // 处于读取消息头部阶段的状态
    const STATUS_READING_HEADER = 0x01;

    // 处于读取消息体阶段的状态
    const STATUS_READING_CONTENT = 0x02;

    protected $receivedData = '';

    /** @var MessageHandler */
    private $handler;

    /** @var DuplexResourceStream */
    private $stream;

    /** @var int 当前状态会持续的在读取消息头部与读取消息体之间变化 */
    private $readStatus = self::STATUS_READING_HEADER;

    /** @var array|null 当前消息解析出的头部 */
    private $header = null;

    public function __construct(DuplexResourceStream $stream, MessageHandler $handler)
    {
        $stream->on("data", [$this, "onReceive"]);
        $this->stream = $stream;
        $this->handler = $handler;
    }

    public function __destruct()
    {
        $this->disconnect();
        unset($this->stream);
        unset($this->handler);
    }

    /**
     * @param Message $msg
     *
     * @return bool
     */
    public function send(Message $msg): bool
    {
        return $this->stream->write(self::serialize($msg));
    }

    /**
     * @param string $data
     *
     * @return void
     * @throws
     */
    public function onReceive(string $data)
    {
        $this->receivedData .= $data;

        while ($message = $this->parseMessages()) {
            $this->handler->handleMessage($message);
        }
    }

    /**
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    /**
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->stream->isWritable();
    }

    /**
     * @return int
     */
    public function getReadStatus(): int
    {
        return $this->readStatus;
    }

    public function disconnect()
    {
        $this->stream->end();
        $this->stream->close();
    }

    /**
     * @param Message $msg
     *
     * @return string
     */
    public static function serialize(Message $msg): string
    {
        $type = Operator::toByteArray($msg->getType(), ByteOrder::LITTLE_ENDIAN);
        $length = Operator::toByteArray($msg->getContentLength(), ByteOrder::LITTLE_ENDIAN);

        return sprintf(
            "%s%s%s%s",
            self::MAGIC_WORD,
            substr($type, 0, self::HEADER_TYPE_SIZE),
            substr($length, 0, self::HEADER_LENGTH_SIZE),
            $msg->getContent()
        );
    }

    /**
     * @param string $header
     *
     * @return array
     * @throws CheckMagicWordException
     */
    public static function parseHeader(string $header): array
    {
        $magicWordLength = strlen(self::MAGIC_WORD);
        $magicWord = substr($header, 0, $magicWordLength);
        // TODO 这里做一个尝试,从找到有magic word的地方恢复
        if ($magicWord !== self::MAGIC_WORD) {
            throw new CheckMagicWordException();
        }

        $type = ord(substr($header, $magicWordLength, self::HEADER_TYPE_SIZE));
        $length = substr($header, $magicWordLength + self::HEADER_TYPE_SIZE, self::HEADER_LENGTH_SIZE);
        $length = unpack("Llength", str_pad($length, 4, "\0"))['length'];

        return [
            'type' => $type,
            'length' => $length,
        ];
    }

    /**
     * @return Message|null
     * @throws
     */
    protected function parseMessages()
    {
        if ($this->readStatus === self::STATUS_READING_HEADER) {
            if (strlen($this->receivedData) < self::HEADER_SIZE) {
                return null;
            }

            $header = self::parseHeader(substr($this->receivedData, 0, self::HEADER_SIZE));
            $this->header = $header;
            $this->readStatus = self::STATUS_READING_CONTENT;
        }

        if ($this->readStatus === self::STATUS_READING_CONTENT) {
            if (strlen($this->receivedData) - self::HEADER_SIZE < $this->header['length']) {
                return null;
            }

            $content = substr($this->receivedData, self::HEADER_SIZE, $this->header['length']);
            $message = new Message($this->header['type'], $content);
            $this->receivedData = substr($this->receivedData, self::HEADER_SIZE + $this->header['length']);
            $this->header = null;
            $this->readStatus = self::STATUS_READING_HEADER;

            return $message;
        }

        return null;
    }
}
