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

abstract class Worker implements HandlerInterface
{
    use SignalTrait;
    use TimerTrait;
    use ErrorTrait;

    /** @var string */
    private $workerID;

    /** @var Communicator */
    private $communicator;

    /** @var LoopInterface */
    protected $loop;

    /**
     * 子类重载构造函数要确保基类构造函数被调用.
     *
     * @param string $id
     * @param resource $socketFD
     * @throws
     */
    function __construct(string $id, $socketFD)
    {
        if (!is_resource($socketFD)) {
            throw new InvalidSocketException();
        }

        $this->workerID = $id;
        $this->loop = Factory::create();
        $stream = new DuplexResourceStream($socketFD, $this->loop);
        $this->communicator = new Communicator($stream, $this);
    }

    function __destruct()
    {
        unset($this->communicator);
        unset($this->loop);
    }

    public function run()
    {
        $this->loop->run();
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