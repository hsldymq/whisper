<?php

namespace Archman\Whisper;

function makeID(): string
{
    return bin2hex(random_bytes(8));
}

/**
 * ID转换为字节数组.
 *
 * @param string $id
 *
 * @return string
 */
function fromID(string $id): string
{
    return hex2bin($id);
}

/**
 * 字节数组还原为ID.
 *
 * @param string $bytes
 *
 * @return string
 */
function fromBytes(string $bytes): string
{
    return bin2hex($bytes);
}