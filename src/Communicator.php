<?php

namespace Archman\Whisper;

class Communicator
{
    const HEADER_SIZE = 13;

    const MAGIC_WORD = "\0\0arch\0\0";

    const STATUS_FIELD_SIZE = 1;

    const LENGTH_FIELD_SIZE = 4;

    /**
     *
     *
     * @param Message $msg
     *
     * @return string
     */
    public static function serialize(Message $msg): string
    {
        $status = strip($msg->getStatus(), self::STATUS_FIELD_SIZE, BO_LE);
        $length = strip($msg->getMessageLength(), self::LENGTH_FIELD_SIZE, BO_LE);

        return sprintf(
            "%s%s%s%s",
            self::MAGIC_WORD,
            pack('C', $status),
            pack('V', $length)
        );
    }
}