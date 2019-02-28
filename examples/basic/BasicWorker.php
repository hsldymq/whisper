<?php

use Archman\Whisper\Message;
use Archman\Whisper\AbstractWorker;

class BasicWorker extends AbstractWorker
{
    public function handleMessage(Message $msg)
    {
        echo "Worker {$this->getWorkerID()} received message, Type:{$msg->getType()}, Content:{$msg->getContent()}\n";

        if ($msg->getType() === 1) {
            exit(0);
        }
    }
}