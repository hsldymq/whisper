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
        $this->assertEquals(['status' => 1, 'length' => 4], $header);

        $msg = new Message(257, '');
        $s = Communicator::serialize($msg);
        $header = Communicator::parseHeader($s);
        $this->assertEquals(['status' => 1, 'length' => 0], $header);
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
}