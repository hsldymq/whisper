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

        if ($stripToSize <= 0) {
            return 0;
        } else if ($stripToSize < PHP_INT_SIZE) {
            $result = $data % pow(256, $stripToSize);
        }

        if ($toByteOrder === BO_LE && getByteOrder() === BO_BE ||
            $toByteOrder === BO_BE && getByteOrder() === BO_LE
        ) {
            $format = PHP_INT_SIZE === 4 ? 'L' : 'Q';
            // 翻转内存的值
            $unpacked = unpack("{$format}result", strrev(pack($format, $result)));
            $result = $unpacked['result'];
        }

        return $result;
    }
}
