<?php

declare(strict_types=1);

namespace Archman\Whisper;

use Archman\Whisper\Exception\InvalidSocketException;
use Archman\Whisper\Exception\UnwritableSocketException;
use Archman\Whisper\Interfaces\MessageHandler;
use Archman\Whisper\Traits\SignalTrait;
use Archman\Whisper\Traits\TerminateTrait;
use Archman\Whisper\Traits\TimerTrait;
use Evenement\EventEmitter;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Stream\DuplexResourceStream;

abstract class AbstractWorker extends EventEmitter implements MessageHandler
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

    /** @var TimerInterface */
    private $processTimer = null;

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

    /**
     * 默认阻塞等待消息到来, 覆盖此方法提供不同的逻辑.
     */
    public function run()
    {
        $this->process();
    }

    public function getWorkerID(): string
    {
        return $this->workerID;
    }

    /**
     * 开始阻塞处理消息传输和处理,直至指定时间返回.
     *
     * @param float|int|null $interval 阻塞时间(秒). 不传代表永久阻塞.
     * @example $master->run(0.1);  // 阻塞100毫秒后返回.
     * @example $master->run(2);    // 阻塞2秒后返回.
     *
     * @throws
     */
    protected function process(float $interval = null)
    {
        if ($interval !== null) {
            $this->processTimer = $this->eventLoop->addTimer($interval, function () {
                $this->eventLoop->stop();
                $this->processTimer = null;
            });
        }

        try {
            $this->eventLoop->run();
        } catch (\Throwable $e) {
            $this->removeProcessTimer();
            throw $e;
        }
    }

    /**
     * 停止阻塞处理.
     */
    protected function stopProcess()
    {
        $this->eventLoop->stop();
        $this->removeProcessTimer();
    }

    /**
     * 移除事件循环的计时器.
     */
    protected function removeProcessTimer()
    {
        if ($this->processTimer) {
            $this->eventLoop->cancelTimer($this->processTimer);
            $this->processTimer = null;
        }
    }

    /**
     * @return LoopInterface
     */
    protected function getEventLoop(): LoopInterface
    {
        return $this->eventLoop;
    }

    /**
     * @return Communicator
     */
    protected function getCommunicator(): Communicator
    {
        return $this->communicator;
    }

    final protected function errorlessEmit(string $event, array $args = [])
    {
        try {
            $this->emit($event, $args);
        } catch (\Throwable $e) {}
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
