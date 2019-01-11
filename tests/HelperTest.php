<?php

use PHPUnit\Framework\TestCase;
use Archman\Whisper;

class HelperTest extends TestCase
{
    public function testA()
    {
        Whisper\setOrderByte(Whisper\BO_BE);
        $this->assertEquals(Whisper\BO_BE, Whisper\getOrderByte());

        Whisper\setOrderByte(Whisper\BO_LE);
        $this->assertEquals(Whisper\BO_LE, Whisper\getOrderByte());
    }
}