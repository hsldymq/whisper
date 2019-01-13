<?php

use PHPUnit\Framework\TestCase;
use Archman\Whisper;

class HelperTest extends TestCase
{
    public function testSetByteOrder()
    {
        Whisper\setByteOrder(Whisper\BO_BE);
        $this->assertEquals(Whisper\BO_BE, Whisper\getByteOrder());

        Whisper\setByteOrder(Whisper\BO_LE);
        $this->assertEquals(Whisper\BO_LE, Whisper\getByteOrder());

        return true;
    }

    /**
     * @depends testSetByteOrder
     */
    public function testStrip($dep)
    {
        $leInt = $this->makeInt("\x31\x32\x33\x34\x35\x36\x37\x38", PHP_INT_SIZE);
        $beInt = $this->makeInt("\x38\x37\x36\x35\x34\x33\x32\x31", PHP_INT_SIZE);

        $cases = [
            "size8" => [
                [
                    'byteOrder' => Whisper\BO_LE,
                    'expect' => ["\x31\x00\x00\x00\x00\x00\x00\x00", 8],
                    'actual' => [$leInt, 1, Whisper\BO_LE],
                    'error' => "Error No.1",
                ],
                [
                    'byteOrder' => Whisper\BO_LE,
                    'expect' => ["\x31\x32\x33\x34\x35\x00\x00\x00", 8],
                    'actual' => [$leInt, 5, Whisper\BO_LE],
                    'error' => "Error No.2",
                ],
                [
                    'byteOrder' => Whisper\BO_LE,
                    'expect' => ["\x31\x32\x33\x34\x35\x36\x37\x38", 8],
                    'actual' => [$leInt, 9, Whisper\BO_LE],
                    'error' => "Error No.3",
                ],
                [
                    'byteOrder' => Whisper\BO_LE,
                    'expect' => ["\x00\x00\x00\x00\x00\x00\x00\x31", 8],
                    'actual' => [$leInt, 1, Whisper\BO_BE],
                    'error' => "Error No.4",
                ],
                [
                    'byteOrder' => Whisper\BO_LE,
                    'expect' => ["\x00\x00\x00\x35\x34\x33\x32\x31", 8],
                    'actual' => [$leInt, 5, Whisper\BO_BE],
                    'error' => "Error No.5",
                ],
                [
                    'byteOrder' => Whisper\BO_BE,
                    'expect' => ["\x00\x00\x00\x00\x00\x00\x00\x31", 8],
                    'actual' => [$beInt, 1, Whisper\BO_BE],
                    'error' => "Error No.6",
                ],
                [
                    'byteOrder' => Whisper\BO_BE,
                    'expect' => ["\x00\x00\x00\x35\x34\x33\x32\x31", 8],
                    'actual' => [$beInt, 5, Whisper\BO_BE],
                    'error' => "Error No.7",
                ],
                [
                    'byteOrder' => Whisper\BO_BE,
                    'expect' => ["\x31\x00\x00\x00\x00\x00\x00\x00", 8],
                    'actual' => [$beInt, 1, Whisper\BO_LE],
                    'error' => "Error No.8",
                ],
                [
                    'byteOrder' => Whisper\BO_BE,
                    'expect' => ["\x31\x32\x33\x34\x35\x00\x00\x00", 8],
                    'actual' => [$beInt, 5, Whisper\BO_LE],
                    'error' => "Error No.9",
                ],
            ],
            'size4' => [
                [
                    [
                        'byteOrder' => Whisper\BO_LE,
                        'expect' => ["\x31\x00\x00\x00", 4],
                        'actual' => [$leInt, 1, Whisper\BO_LE],
                        'error' => "Error No.1",
                    ],
                    [
                        'byteOrder' => Whisper\BO_LE,
                        'expect' => ["\x31\x32\x33\x00", 4],
                        'actual' => [$leInt, 3, Whisper\BO_LE],
                        'error' => "Error No.2",
                    ],
                    [
                        'byteOrder' => Whisper\BO_LE,
                        'expect' => ["\x31\x32\x33\x34", 4],
                        'actual' => [$leInt, 5, Whisper\BO_LE],
                        'error' => "Error No.3",
                    ],
                    [
                        'byteOrder' => Whisper\BO_LE,
                        'expect' => ["\x00\x00\x00\x31", 8],
                        'actual' => [$leInt, 1, Whisper\BO_BE],
                        'error' => "Error No.4",
                    ],
                    [
                        'byteOrder' => Whisper\BO_LE,
                        'expect' => ["\x00\x33\x32\x31", 8],
                        'actual' => [$leInt, 3, Whisper\BO_BE],
                        'error' => "Error No.5",
                    ],
                    [
                        'byteOrder' => Whisper\BO_BE,
                        'expect' => ["\x00\x00\x00\x31", 4],
                        'actual' => [$beInt, 1, Whisper\BO_BE],
                        'error' => "Error No.6",
                    ],
                    [
                        'byteOrder' => Whisper\BO_BE,
                        'expect' => ["\x00\x33\x32\x31", 4],
                        'actual' => [$beInt, 3, Whisper\BO_BE],
                        'error' => "Error No.7",
                    ],
                    [
                        'byteOrder' => Whisper\BO_BE,
                        'expect' => ["\x31\x00\x00\x00", 4],
                        'actual' => [$beInt, 1, Whisper\BO_LE],
                        'error' => "Error No.8",
                    ],
                    [
                        'byteOrder' => Whisper\BO_BE,
                        'expect' => ["\x31\x32\x33\x00", 4],
                        'actual' => [$beInt, 3, Whisper\BO_LE],
                        'error' => "Error No.9",
                    ],
                ],
            ]
        ];

        $c = PHP_INT_SIZE === 8 ? $cases['size8'] : $cases['size4'];

        foreach ($c as $each) {
            Whisper\setByteOrder($each['byteOrder']);
            $this->assertEquals(
                $this->makeInt(...$each['expect']),
                Whisper\strip(...$each['actual']),
                $each['error']
            );
        }
    }

    /**
     * @before
     */
    public function resetByteOrder()
    {
        Whisper\resetByteOrder();
    }

    private function makeInt(string $byteArray, int $intSize): int
    {
        if ($intSize === 4) {
            $result = unpack("Vint", $byteArray);
        } else {
            $result = unpack("Pint", $byteArray);
        }

        return $result['int'];
    }
}