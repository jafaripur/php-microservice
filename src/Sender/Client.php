<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\Queue;

final class Client
{
    /**
     *
     * @param  Queue  $queue
     */
    public function __construct(private Queue $queue)
    {
    }

    /**
     * Create async command and send several message at once
     *
     * @param  integer $timeout as millisecond
     * @return AsyncSender
     */
    final public function async(int $timeout = AsyncSender::COMMAND_ASYNC_MESSAGE_TIMEOUT): AsyncSender
    {
        return new AsyncSender($this->queue, $timeout);
    }

    /**
     * Create command for sending and waiting to get response back
     *
     * @return CommandSender
     */
    public function command(bool $passive = true): CommandSender
    {
        return new CommandSender($this->queue, $passive);
    }

    /**
     * Create emit for sending
     *
     * @return EmitSender
     */
    public function emit(bool $passive = true): EmitSender
    {
        return new EmitSender($this->queue, $passive);
    }

    /**
     * Create topic for sending
     *
     * @return TopicSender
     */
    public function topic(bool $passive = true): TopicSender
    {
        return new TopicSender($this->queue, $passive);
    }

    /**
     * Create worker for sending
     *
     * @return WorkerSender
     */
    public function worker(bool $passive = true): WorkerSender
    {
        return new WorkerSender($this->queue, $passive);
    }
}
