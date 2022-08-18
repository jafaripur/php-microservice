<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Araz\MicroService\Processors\Command;
use Araz\MicroService\Processors\Emit;
use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Processors\RequestResponse\RequestTopic;
use Araz\MicroService\Processors\Topic;
use Araz\MicroService\Processors\Worker;
use Interop\Queue\Consumer as AmqpConsumer;
use Interop\Queue\Message as AmqpMessage;

// use Interop\Amqp\AmqpConsumer;
// use Interop\Amqp\Impl\AmqpMessage;

/**
 * If service container availale, dependencies will inject in creation of object __construct(...).
 */
abstract class Processor
{
    public const ACK = 'ack';

    public const REJECT = 'reject';

    public const REQUEUE = 'requeue';

    private Queue $queue;

    private ProcessorConsumer $processorConsumer;

    /**
     * Get type of processor.
     */
    abstract public static function getType(): string;

    /**
     * Get name of the queue for consuming.
     */
    abstract public function getQueueName(): string;

    /**
     * Run before the main action (execute)
     * With returning false, message => reject.
     *
     * @param Request|RequestTopic $request received data
     */
    public function beforeExecute(Request|RequestTopic $request): bool
    {
        return true;
    }

    /**
     * Run after the main action for event or command.
     *
     * @param Request|RequestTopic $request received data
     */
    public function afterExecute(Request|RequestTopic $request): void
    {
    }

    public function init(): void
    {
        $this->validateProcessor();
    }

    /**
     * Get current running queue object.
     */
    public function getQueue(): Queue
    {
        return $this->queue;
    }

    /**
     * Get current running processor consumer.
     */
    public function getProcessorConsumer(): ProcessorConsumer
    {
        return $this->processorConsumer;
    }

    /**
     * Set current running queue object.
     */
    public function setQueue(Queue $queue): void
    {
        $this->queue = $queue;
    }

    /**
     * Set current running processor consumer.
     */
    public function setProcessorConsumer(ProcessorConsumer $processorConsumer): void
    {
        $this->processorConsumer = $processorConsumer;
    }

    /**
     * Run after the afterExecute method with returning this value:
     * available option: self::ACK, self::REJECT, self::REQUEUE
     * If you want to reject or requeue the message, this method can be implement in your processor.
     *
     * @return string this can be self::ACK, self::REJECT, self::REQUEUE
     */
    public function process(AmqpMessage $message, AmqpConsumer $consumer): string
    {
        return self::ACK;
    }

    /**
     * Run after the message acknowledged to queue server.
     *
     * @param string $status ack, reject, requeue
     */
    public function afterMessageAcknowledge(string $status): void
    {
    }

    /**
     * Reset processor object to default after execute.
     */
    public function resetAfterProcess(): bool
    {
        return false;
    }

    /**
     * Trigger when processor finished.
     *
     * @param string $status ack, reject, requeue, null on redelivery
     */
    public function processorFinished(?string $status): void
    {
    }

    /**
     * Queue ttl as millisecond.
     *
     * @return int time as millisecond
     */
    public function getQueueTtl(): int
    {
        return $this->queue::QUEUE_DEFAULT_TTL;
    }

    final public function isCommand(): bool
    {
        return $this instanceof Command;
    }

    final public function isWorker(): bool
    {
        return $this instanceof Worker;
    }

    final public function isTopic(): bool
    {
        return $this instanceof Topic;
    }

    final public function isEmit(): bool
    {
        return $this instanceof Emit;
    }

    /**
     * Validate processor parameters.
     */
    abstract protected function validateProcessor(): void;
}
