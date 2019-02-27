<?php

namespace Archman\Whisper\Interfaces;

use Archman\Whisper\Worker;

interface WorkerFactoryInterface
{
    public function makeWorker(string $id, $socketFD): Worker;
}