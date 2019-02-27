<?php

use Archman\Whisper\Message;
use Archman\Whisper\Worker as BaseWorker;

class Worker extends BaseWorker
{
    public function handleMessage(Message $msg)
    {
        echo "Worker {$this->getWorkerID()} received message, Type:{$msg->getType()}, Content:{$msg->getContent()}\n";

        if ($msg->getType() === 1) {
            exit(0);
        }
    }
}