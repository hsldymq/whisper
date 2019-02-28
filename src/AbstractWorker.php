<?php

namespace Archman\Whisper;

use Archman\Whisper\Exception\InvalidSocketException;
use Archman\Whisper\Exception\UnwritableSocketException;
use Archman\Whisper\Interfaces\HandlerInterface;
use Archman\Whisper\Traits\ErrorTrait;
use Archman\Whisper\Traits\SignalTrait;
use Archman\Whisper\Traits\TimerTrait;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;

abstract class AbstractWorker implements HandlerInterface
{
    use SignalTrait;
    use TimerTrait;
    use ErrorTrait;

    /** @var string */
    private $workerID;

    /** @var Communicator */
    private $communicator;

    /** @var LoopInterface */
    protected $eventLoop;

    /**
     * 子类重载构造函数要确保基类构造函数被调用.
     *
     * @param string $id
     * @param resource $socketFD
     * @throws
     */
    function __construct(string $id, $socketFD)
    {
        if (!is_resource($socketFD) || get_resource_type($socketFD) !== 'stream' || feof($socketFD)) {
            throw new InvalidSocketException();
        }

        $this->workerID = $id;
        $this->eventLoop = Factory::create();
        $stream = new DuplexResourceStream($socketFD, $this->eventLoop);
        $this->communicator = new Communicator($stream, $this);
    }

    function __destruct()
    {
        unset($this->communicator);
        unset($this->eventLoop);
    }

    public function run()
    {
        $this->eventLoop->run();
    }

    public function getWorkerID(): string
    {
        return $this->workerID;
    }

    final public function handleError(\Throwable $e)
    {
        $this->raiseError($e);
    }

    final protected function sendMessage(Message $msg)
    {
        if (!$this->communicator->isWritable()) {
            $this->raiseError(new UnwritableSocketException());
            return;
        }

        $this->communicator->send($msg);
    }
}