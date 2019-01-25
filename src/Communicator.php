<?php

namespace Archman\Whisper;

use Archman\ByteOrder\ByteOrder;
use Archman\ByteOrder\Operator;
use Archman\Whisper\Exception\CheckMagicWordException;
use Archman\Whisper\Exception\InvalidSocketException;

class Communicator
{
    // 消息头总长度
    const HEADER_SIZE = 12;

    // 头部magic word,用于标志一个正确的消息开始
    const MAGIC_WORD = "\0\0arch\0\0";

    // 头部消息类型字段长度(字节)
    const HEADER_TYPE_SIZE = 1;

    // 头部消息体长度字段长度(字节)
    // 一个消息体最多容纳16M载荷
    const HEADER_LENGTH_SIZE = 3;

    // 处于读取消息头部阶段的状态
    const STATUS_READING_HEADER = 0x01;

    // 处于读取消息体阶段的状态
    const STATUS_READING_CONTENT = 0x02;

    const STATUS_SOCKET_READY = 0x10;

    const STATUS_SOCKET_READONLY = 0x11;

    const STATUS_SOCKET_WRITEONLY = 0x12;

    const STATUS_SOCKET_CLOSED = 0x13;

    protected $receiveBuffer = '';

    protected $sendBuffer = '';

    /** @var resource 与子/主进程通信的socket文件描述符 */
    private $socketFD;

    /** @var int 当前状态会持续的在读取消息头部与读取消息体之间变化 */
    private $readStatus = self::STATUS_READING_HEADER;

    private $status = self::STATUS_SOCKET_READY;

    /** @var array|null 当前消息解析出的头部 */
    private $header = null;

    /** @var callable */
    private $onMessageHandler;

    /** @var callable */
    private $onErrorHandler;

    public function __construct($socket)
    {
        $this->socketFD = $socket;

        if ($this->getStatus() === self::STATUS_SOCKET_CLOSED) {
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

    public function onReceive()
    {
        $received = stream_socket_recvfrom($this->socketFD, 65535);
        if (strlen($received) === 0 && $this->isSocketClosed()) {
            throw new InvalidSocketException();
        }

        $this->receiveBuffer .= $received;
        try {
            while ($message = $this->parseMessages()) {
                if (is_callable($this->onMessageHandler)) {
                    $this->onMessageHandler($message);
                }
            }
        } catch (\Throwable $e) {
            if (is_callable($this->onErrorHandler)) {
                $this->onErrorHandler($e);
            } else {
                throw $e;
            }
        }
    }

    public function onSend()
    {

    }

    public function getReadStatus(): int
    {
        return $this->readStatus;
    }

    public function getStatus(): int
    {
        if (in_array($this->status, [self::STATUS_SOCKET_READY, self::STATUS_SOCKET_READONLY, self::STATUS_SOCKET_WRITEONLY])) {
            if ($this->isSocketClosed()) {
                $this->status = self::STATUS_SOCKET_CLOSED;
            }
        }

        return $this->status;
    }

    public function getSocket()
    {
        return $this->socketFD;
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

    public static function parseHeader(string $header): array
    {
        $magicWordLength = strlen(self::MAGIC_WORD);
        $magicWord = substr($header, 0, $magicWordLength);
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
            if (strlen($this->receiveBuffer) < self::HEADER_SIZE) {
                return null;
            }

            $header = self::parseHeader(substr($this->receiveBuffer, 0, self::HEADER_SIZE));
            $this->header = $header;
            $this->readStatus = self::STATUS_READING_CONTENT;
        }

        if ($this->readStatus === self::STATUS_READING_CONTENT) {
            if (strlen($this->receiveBuffer) - self::HEADER_SIZE < $this->header['length']) {
                return null;
            }

            $content = substr($this->receiveBuffer, self::HEADER_SIZE, $this->header['length']);
            $message = new Message($this->header['type'], $content);
            $this->receiveBuffer = substr($this->receiveBuffer, self::HEADER_SIZE + $this->header['length']);
            $this->header = null;
            $this->readStatus = self::STATUS_READING_HEADER;

            return $message;
        }

        return null;
    }

    private function isSocketClosed(): bool
    {
        return !is_resource($this->socketFD) || feof($this->socketFD);
    }
}