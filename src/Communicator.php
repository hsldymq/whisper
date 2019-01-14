<?php

namespace Archman\Whisper;

use Archman\Whisper\Exception\CheckMagicWordException;
use Archman\Whisper\Exception\InvalidSocketException;

class Communicator
{
    // 消息头总长度
    const HEADER_SIZE = 13;

    // 头部magic word,用于标志一个正确的消息开始
    const MAGIC_WORD = "\0\0arch\0\0";

    // 头部状态字段长度(字节)
    const STATUS_FIELD_SIZE = 1;

    // 头部消息体长度字段长度(字节)
    const LENGTH_FIELD_SIZE = 4;

    // 处于读取消息头部阶段的状态
    const STATUS_READING_HEADER = 0x01;

    // 处于读取消息体阶段的状态
    const STATUS_READING_CONTENT = 0x02;

    /** @var resource 与子/主进程通信的socket文件描述符 */
    private $socketFD;

    /** @var int 当前状态会持续的在读取消息头部与读取消息体之间变化 */
    private $status = self::STATUS_READING_HEADER;

    /** @var array 当前消息解析出的字段 */
    private $header = [
        'status' => null,
        'length' => null,
    ];

    /** @var callable */
    private $onMessageHandler;

    /** @var callable */
    private $onErrorHandler;

    private $receiveBuffer = '';

    private $sendBuffer = '';

    public function __construct($socket)
    {
        $this->socketFD = $socket;

        if ($this->isSocketClosed()) {
            throw new InvalidSocketException();
        }
    }

    public function __destruct()
    {
        if (!$this->isSocketClosed()) {
            fclose($this->socketFD);
        }
        if ($this->onMessageHandler) {
            unset($this->onErrorHandler);
        }
        if ($this->onErrorHandler) {
            unset($this->onMessageHandler);
        }
    }

    /**
     *
     *
     * @param Message $msg
     *
     * @return string
     */
    public static function serialize(Message $msg): string
    {
        $status = strip($msg->getStatus(), self::STATUS_FIELD_SIZE, BO_LE);
        $length = strip($msg->getContentLength(), self::LENGTH_FIELD_SIZE, BO_LE);

        return sprintf(
            "%s%s%s%s",
            self::MAGIC_WORD,
            pack('C', $status),
            pack('V', $length),
            $msg->getContent()
        );
    }

    public static function parseHeader(string $header): array
    {
        $magicWordLength = strlen(self::MAGIC_WORD);
        $magicWord = substr($header, 0, $magicWordLength);
        if ($magicWord !== self::MAGIC_WORD) {
            throw new CheckMagicWordException();
        }

        $status = ord(substr($header, $magicWordLength, self::STATUS_FIELD_SIZE));
        $length = substr($header, $magicWordLength + self::STATUS_FIELD_SIZE, self::LENGTH_FIELD_SIZE);
        $length = unpack("Llength", $length)['length'];

        return [
            'status' => $status,
            'length' => $length,
        ];
    }

    private function isSocketClosed(): bool
    {
        return !is_resource($this->socketFD) || feof($this->socketFD);
    }
}