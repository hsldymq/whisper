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
    }
}