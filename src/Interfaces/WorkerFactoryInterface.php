<?php

namespace Archman\Whisper\Interfaces;

interface WorkerFactoryInterface
{
    public function makeWorker($socketFD): Worker;
}