<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\Queue;

final class Client
{
    /**
     *
     * @var Queue $queue
     */
    private Queue $queue;

    /**
     *
     * @param  Queue  $queue
     * @param  string $asyncSenderClass
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
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
    public function command(): CommandSender
    {
        return new CommandSender($this->queue);
    }

    /**
     * Create emit for sending
     *
     * @return EmitSender
     */
    public function emit(): EmitSender
    {
        return new EmitSender($this->queue);
    }

    /**
     * Create topic for sending
     *
     * @return TopicSender
     */
    public function topic(): TopicSender
    {
        return new TopicSender($this->queue);
    }

    /**
     * Create worker for sending
     *
     * @return WorkerSender
     */
    public function worker(): WorkerSender
    {
        return new WorkerSender($this->queue);
    }
}
