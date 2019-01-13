<?php

namespace Archman\Whisper;

// 小端字节序
const BO_LE = 0;

// 大端字节序
const BO_BE = 1;

// 主机字节序
const BO_HOST = 2;

if (!function_exists(__NAMESPACE__.'\\getByteOrder')) {
    /**
     * 获得当前机器的字节序.
     *
     * @return int
     */
    function getByteOrder(): int
    {
        static $order;

        if ($order === null) {
            if (pack('v', 0x305A) === pack('S', 0x305A)) {
                $order = BO_LE;
            } else {
                $order = BO_BE;
            }
        }

        return $order;
    }
}

if (!function_exists(__NAMESPACE__.'\\isLittleEndian')) {
    /**
     * 当前机器的是否是小端字节序.
     *
     * @return bool
     */
    function isLittleEndian(): bool
    {
        return getByteOrder() === BO_LE;
    }
}

if (!function_exists(__NAMESPACE__.'\\strip')) {
    function strip(int $data, int $stripToSize, int $toByteOrder = BO_HOST): int
    {
        $result = $data;
        $format = PHP_INT_SIZE === 8 ? 'P' : 'V';

        if ($stripToSize <= 0) {
            return 0;
        } else if ($stripToSize < PHP_INT_SIZE) {
            $packed = pack($format, $result);
            if (isLittleEndian()) {
                $packed = str_pad(substr($packed, 0, $stripToSize), PHP_INT_SIZE, "\x00");
            } else {
                $packed = str_pad(substr($packed, -$stripToSize), PHP_INT_SIZE, "\x00", STR_PAD_LEFT);
            }
            $result = unpack("{$format}result", $packed)['result'];
        }

        if ($toByteOrder === BO_LE && getByteOrder() === BO_BE ||
            $toByteOrder === BO_BE && getByteOrder() === BO_LE
        ) {
            // 翻转内存的值
            $unpacked = unpack("{$format}result", strrev(pack($format, $result)));
            $result = $unpacked['result'];
        }

        return $result;
    }
}

if (!function_exists(__NAMESPACE__.'\\uuid')) {
    /**
     * 生成v4版本的UUID.
     */
    function uuid(): string
    {
        $uuid = bin2hex(random_bytes(18));
        $uuid[8] = $uuid[13] = $uuid[18] = $uuid[23] = '-';
        $uuid[14] = '4';
        $uuid[19] = dechex(hexdec($uuid[19]) & 3 | 8);

        return $uuid;
    }
}