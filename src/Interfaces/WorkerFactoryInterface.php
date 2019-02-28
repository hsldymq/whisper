<?php

namespace Archman\Whisper\Interfaces;

use Archman\Whisper\AbstractWorker;

interface WorkerFactoryInterface
{
    public function makeWorker(string $id, $socketFD): AbstractWorker;
}