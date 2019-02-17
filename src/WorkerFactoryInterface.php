<?php

namespace Archman\Whisper;

interface WorkerFactoryInterface
{
    public function makeWorker($socketFD): Worker;
}