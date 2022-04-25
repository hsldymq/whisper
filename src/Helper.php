<?php

declare(strict_types=1);

namespace Archman\Whisper;

class Helper
{
    public static function uuid(): string
    {
        $uuid = bin2hex(random_bytes(18));
        $uuid[8] = $uuid[13] = $uuid[18] = $uuid[23] = '-';
        $uuid[14] = '4';
        $uuid[19] = dechex(hexdec($uuid[19]) & 3 | 8);

        return $uuid;
    }
}
