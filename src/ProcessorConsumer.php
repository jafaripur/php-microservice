<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Generator;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpMessage;

/**
 * If service container availale, dependencies will inject in creation of object __construct(...)
 */
abstract class ProcessorConsumer
{
    /**
     * Maximum time of redeliver message again to queue
     */
    public const MAX_RETRY_REDELIVER = 5;

    /**
     * Delay time as millisecond for redeliver message to queue
     */
    public const MAX_RETRY_REDELIVER_DELAY = 0;

    protected Queue $queue;

    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Get list of processors classes
     */
    abstract public function getProcessors(): Generator;

    /**
     * Get consumer identify name, should be unique in available ProcessorConsumers.
     *
     * @return string
     */
    abstract public function getConsumerIdentify(): string;

    /**
     * Run when consumer get receive new message
     *
     * @param  AmqpMessage  $message
     * @param  AmqpConsumer $consumer
     * @return void
     */
    public function messageReceived(AmqpMessage $message, AmqpConsumer $consumer): void
    {
    }

    /**
     * Run when a message is redelivered by consumer
     *
     * @param  AmqpMessage  $message
     * @param  AmqpConsumer $consumer
     * @return void
     */
    public function messageRedelivered(AmqpMessage $message, AmqpConsumer $consumer): void
    {
    }

    /**
     * Run after the message acknowledge
     *
     * @param  string       $status   reject, ack, reject
     * @param  AmqpMessage  $message
     * @param  AmqpConsumer $consumer
     * @return void
     */
    public function afterMessageAcknowledge(string $status, AmqpMessage $message, AmqpConsumer $consumer): void
    {
    }

    /**
     * Run when maximum limit reached in the message redelivery
     *
     * @param  AmqpMessage  $message
     * @param  AmqpConsumer $consumer
     * @return void
     */
    public function messageRedeliveredMaximumReached(AmqpMessage $message, AmqpConsumer $consumer): void
    {
    }

    /**
     * Enable single active consumer.
     * Just for workers and commands method.
     * Emit and Topic not support this one and by default they are already single active consumer
     *
     * @return false
     */
    public function getSingleActiveConsumer(): bool
    {
        return false;
    }

    /**
     * Get maximum number of try for redeliver a message to queue
     *
     * @psalm-return 5
     */
    public function getMaxRedeliveryRetry(): int
    {
        return self::MAX_RETRY_REDELIVER;
    }

    /**
     * If a message require for redelivery, delay time to push again message to queue, 0 = no delay, as millisecond
     *
     * @return int as millisecond
     */
    public function getRedeliveryDelayTime(): int
    {
        return self::MAX_RETRY_REDELIVER_DELAY;
    }

    /**
     * Get prefetch count
     *
     * @return int
     *
     * @psalm-return 1
     */
    public function getPrefetchCount(): int
    {
        return 1;
    }

    /**
     * Set a consumer queue is durable
     * Just for worker, topic, emit
     * Command is not durable by default
     *
     * @return bool
     *
     */
    public function durableQueue(): bool
    {
        return true;
    }
}
