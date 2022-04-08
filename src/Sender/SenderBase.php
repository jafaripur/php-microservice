<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\Queue;

abstract class SenderBase
{
    /**
     *
     * @param  Queue  $queue
     */
    public function __construct(
        protected Queue $queue,
        private bool $passive
    ) {
    }

    abstract public function send(): mixed;

    public function getPassive(): bool
    {
        return $this->passive;
    }

    public function getQueue(): Queue
    {
        return $this->queue;
    }
}
