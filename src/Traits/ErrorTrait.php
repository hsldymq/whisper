<?php

declare(strict_types=1);

namespace Archman\Whisper\Traits;

use Archman\Whisper\Interfaces\ErrorHandlerInterface;

trait ErrorTrait
{
    /**
     * 当Master, Worker实现了ErrorHandlerInterface,所有的异常会被捕捉并转发, 否则直接抛异常.
     * @param \Throwable $e
     * @throws \Throwable
     */
    final public function raiseError(\Throwable $e)
    {
        if ($this instanceof ErrorHandlerInterface) {
            $this->onError($e);
            return;
        }

        throw $e;
    }
}
