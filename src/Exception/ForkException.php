<?php

namespace Archman\Whisper\Exception;

class ForkException extends \Exception
{
    const DAEMONIZING = 1;

    const CREATING = 2;
}