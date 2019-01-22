<?php

use PHPUnit\Framework\TestCase;
use Archman\Whisper\Message;
use Archman\Whisper\Communicator;

class CommunicatorTest extends TestCase
{
    public function testSerialize()
    {
        $content = str_repeat('a', 0x1234);
        $msg = new Message(1, $content);
        $expect = Communicator::MAGIC_WORD."\x01\x34\x12\x00\x00{$content}";
        $this->assertEquals($expect, Communicator::serialize($msg));

        $msg = new Message(0, '');
        $expect = Communicator::MAGIC_WORD."\x00\x00\x00\x00\x00";
        $this->assertEquals($expect, Communicator::serialize($msg));

        $msg = new Message(257, 'abc');
        $expect = Communicator::MAGIC_WORD."\x01\x03\x00\x00\x00abc";
        $this->assertEquals($expect, Communicator::serialize($msg));
    }

    /**
     * @depends testSerialize
     */
    public function testParseHeader()
    {
        $msg = new Message(1, 'xxxx');
        $s = Communicator::serialize($msg);
        $header = Communicator::parseHeader($s);
        $this->assertEquals(['type' => 1, 'length' => 4], $header);

        $msg = new Message(257, '');
        $s = Communicator::serialize($msg);
        $header = Communicator::parseHeader($s);
        $this->assertEquals(['type' => 1, 'length' => 0], $header);
    }

    /**
     * @depends testParseHeader
     * @expectedException \Archman\Whisper\Exception\CheckMagicWordException
     */
    public function testParseInvalidHeader()
    {
        $msg = new Message(1, 'xxxx');
        $s = Communicator::serialize($msg);
        $s[0] = 'x';
        Communicator::parseHeader($s);
    }

    /**
     * @depends testParseHeader
     */
    public function testParseMessages()
    {
        $communicator = new class() extends Communicator {
            public function __construct() {}
        };

        $cat = (function ($data) {
            $this->receiveBuffer .= $data;
        })->bindTo($communicator, $communicator);

        $parser = (function () {
            return $this->parseMessages();
        })->bindTo($communicator, $communicator);

        $this->assertEquals(Communicator::STATUS_READING_HEADER, $communicator->getStatus());

        $msg = Communicator::serialize(new Message(1, 'abc'));
        $cat($msg);
        /** @var Message $message */
        $message = $parser();
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals("abc", $message->getContent());
        $this->assertEquals(Communicator::STATUS_READING_HEADER, $communicator->getStatus());

        $msg = Communicator::serialize(new Message(1, 'xyz'));
        // 只写入一部分头部
        $cat(substr($msg, 0, 11));
        $this->assertNull($parser());
        $this->assertEquals(Communicator::STATUS_READING_HEADER, $communicator->getStatus());

        // 将头部剩下部分连带一个字节的内容部分写入
        $cat(substr($msg, 11, 3));
        $this->assertNull($parser());
        $this->assertEquals(Communicator::STATUS_READING_CONTENT, $communicator->getStatus());

        // 将剩余内容写入
        $cat(substr($msg, 14));
        $message = $parser();
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('xyz', $message->getContent());
        $this->assertEquals(Communicator::STATUS_READING_HEADER, $communicator->getStatus());

    }
}