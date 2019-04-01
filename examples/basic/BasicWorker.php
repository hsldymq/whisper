<?php

use Archman\Whisper\Message;
use Archman\Whisper\AbstractWorker;

class BasicWorker extends AbstractWorker
{
    public function __construct(string $id, $socketFD)
    {
        parent::__construct($id, $socketFD);

        $this->sendMessage(new Message(0, "Worker {$this->getWorkerID()} Is Ready"));
    }

    public function handleMessage(Message $msg)
    {
        if ($msg->getType() === 10) {
            echo "Received A Quit Message From Master\n";
            exit(0);
        }

        echo "Received A Message From Master, Type:{$msg->getType()}, Content:{$msg->getContent()}\n";

        sleep(1);

        $this->sendMessage(new Message(1, "This Message Was Sent By Worker {$this->getWorkerID()}."));
    }
}