<?php

declare(strict_types=1);

namespace Archman\Whisper;

use Archman\Whisper\Exception\InvalidSocketException;
use Archman\Whisper\Exception\UnwritableSocketException;
use Archman\Whisper\Interfaces\MessageHandler;
use Archman\Whisper\Traits\SignalTrait;
use Archman\Whisper\Traits\TerminateTrait;
use Archman\Whisper\Traits\TimerTrait;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;

abstract class AbstractWorker implements MessageHandler
{
    use SignalTrait;
    use TimerTrait;
    use TerminateTrait;

    /** @var string */
    private $workerID;

    /** @var Communicator */
    private $communicator;

    /** @var LoopInterface */
    private $eventLoop;

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

    protected function getEventLoop(): LoopInterface
    {
        return $this->eventLoop;
    }

    /**
     * @param Message $msg
     * @throws
     */
    final protected function sendMessage(Message $msg)
    {
        if (!$this->communicator->isWritable()) {
            throw new UnwritableSocketException();
        }

        $this->communicator->send($msg);
    }
}
