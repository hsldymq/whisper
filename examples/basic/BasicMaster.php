<?php

require_once __DIR__.'/WorkerFactory.php';

use Archman\Whisper\Message;
use Archman\Whisper\AbstractMaster;

class BasicMaster extends AbstractMaster
{
    private $childNum = 10;

    public function __construct()
    {
        parent::__construct();

        $this->on('__workerExit', function (string $workerID) {
            echo "{$workerID} Quit.\n";

            if ($this->countWorkers() === 0) {
                echo "Master Quit\n";
                $this->stopProcess();
            }
        });

        $this->addSignalHandler(SIGINT, function () {
            echo "Sending Quit Message To Child.\n";
            foreach ($this->getWorkerIDs() as $id) {
                $this->sendMessage($id, new Message(10, ''));
            }
        });

        $this->registerShutdown(function () {
            echo "Shutdown Function Called.\n";
        });
    }

    public function run()
    {
        echo "Press Enter To Start. After Started, Press CTRL+C To Exit.\n";
        fgets(STDIN);

        $factory = new WorkerFactory();
        $workerIDs = [];

        for ($i = 0; $i < $this->childNum; $i++) {
            $workerIDs[] = $this->createWorker($factory);
        }

        $this->process();
    }

    public function onMessage(string $workerID, Message $msg)
    {
        echo "Receive A Message From Worker, Type:{$msg->getType()}, Content:{$msg->getContent()}\n";

        $this->sendMessage($workerID, new Message(0, "This Message Was Sent By Master."));
    }
}