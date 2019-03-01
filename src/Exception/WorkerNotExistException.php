<?php

namespace Archman\Whisper\Exception;

use Throwable;

class WorkerNotExistException extends \Exception
{
    private $workerID;

    public function __construct(string $workerID, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->workerID = $workerID;
    }

    public function getWorkerID(): string
    {
        return $this->workerID;
    }
}