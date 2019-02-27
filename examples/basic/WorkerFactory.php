<?php

require_once __DIR__.'/Worker.php';

use Archman\Whisper\Interfaces\WorkerFactoryInterface;
use Archman\Whisper\Worker as BaseWorker;

class WorkerFactory implements WorkerFactoryInterface
{
    public function makeWorker(string $id, $socketFD): BaseWorker
    {
        return new Worker($id, $socketFD);
    }
}