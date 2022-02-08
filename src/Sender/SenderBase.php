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

    /**
     *
     * @param  Queue  $queue
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    abstract public function send(): mixed;
}
