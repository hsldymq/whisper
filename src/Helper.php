<?php

namespace Archman\Whisper;

class Helper
{
    public static function uuid(): string
    {
        try {
            $uuid = bin2hex(random_bytes(18));
            $uuid[8] = $uuid[13] = $uuid[18] = $uuid[23] = '-';
            $uuid[14] = '4';
            $uuid[19] = dechex(hexdec($uuid[19]) & 3 | 8);

            return $uuid;
        } catch (\Throwable $e) {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
    }
}