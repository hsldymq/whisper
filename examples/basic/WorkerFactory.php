<?php

require_once __DIR__.'/AbstractWorker.php';

use Archman\Whisper\Interfaces\WorkerFactoryInterface;
use Archman\Whisper\AbstractWorker as BaseWorker;

class WorkerFactory implements WorkerFactoryInterface
{
    public function makeWorker(string $id, $socketFD): BaseWorker
    {
        return new AbstractWorker($id, $socketFD);
    }
}