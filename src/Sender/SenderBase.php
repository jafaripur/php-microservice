<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\Queue;

abstract class SenderBase
{
    /**
      *
      * @var Queue $queue
      */
    protected Queue $queue;

    private bool $passive;

    /**
     *
     * @param  Queue  $queue
     */
    public function __construct(Queue $queue, bool $passive)
    {
        $this->queue = $queue;
        $this->passive = $passive;
    }

    abstract public function send(): mixed;

    public function getPassive(): bool {
      return $this->passive;
    }
}
