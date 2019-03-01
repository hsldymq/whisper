<?php

require_once __DIR__.'/WorkerFactory.php';

use Archman\Whisper\Message;
use Archman\Whisper\AbstractMaster;

class BasicMaster extends AbstractMaster
{
    public function __construct()
    {
        parent::__construct();

        $this->daemonize();

        $this->on('workerExit', function (string $workerID) {
            $num = $this->countWorkers();
            echo "{$workerID} Quit. Number of workers: {$num}\n";
            if ($num === 0) {
                echo "Master Quit.\n";
                exit(0);
            }
        });
    }

    public function run()
    {
        $factory = new WorkerFactory();
        $workerIDs = [];
        $workerIDs[] = $this->createWorker($factory);
        $workerIDs[] = $this->createWorker($factory);
        $workerIDs[] = $this->createWorker($factory);

        foreach ($workerIDs as $id) {
            $this->sendMessage($id, new Message(0, "This message is sending to {$id}."));
            $this->sendMessage($id, new Message(1, ""));
        }

        $this->process();
    }

    public function onMessage(string $workerID, Message $msg)
    {
        echo "Worker ID:{$workerID}, Type:{$msg->getType()}, Content:{$msg->getContent()}\n";
    }
}