<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpMessage;

/**
 * If service container availale, dependencies will inject in creation of object __construct(...)
 */
abstract class Processor
{
    public const ACK = 'ack';
    public const REJECT = 'reject';
    public const REQUEUE = 'requeue';

    private ProcessorConsumer $processorConsumer;
    private Queue $queue;

    public function __construct(Queue $queue, ProcessorConsumer $processorConsumer)
    {
        $this->queue = $queue;
        $this->processorConsumer = $processorConsumer;
    }

    /**
     * Get current running queue object
     *
     * @return Queue
     */
    public function getQueue(): Queue
    {
        return $this->queue;
    }

    /**
     * Get current running processor consumer
     *
     * @return ProcessorConsumer
     */
    public function getProcessorConsumer(): ProcessorConsumer
    {
        return $this->processorConsumer;
    }

    /**
     * Get name of the queue for consuming
     *
     * @return string
     */
    abstract public function getQueueName(): string;

    /**
     * Validate processor parameters
     *
     * @return void
     */
    abstract public function validateProcessor(): void;

    /**
     * Run after the afterExecute method with returning this value:
     * available option: self::ACK, self::REJECT, self::REQUEUE
     * If you want to reject or requeue the message, this method can be implement in your processor.
     *
     * @param AmqpMessage $message
     * @param AmqpConsumer $consumer
     *
     * @return string this can be self::ACK, self::REJECT, self::REQUEUE
     *
     */
    public function process(AmqpMessage $message, AmqpConsumer $consumer): string
    {
        return self::ACK;
    }

    /**
     * Run before the main action (execute)
     * With returning false, message => reject
     *
     * @param mixed   $data  received data
     *
     * @return bool
     */
    public function beforeExecute(mixed $data): bool
    {
        return true;
    }

    /**
     * Run after the main action for event or command
     *
     * @param  mixed $data received data
     * @return void
     */
    public function afterExecute(mixed $data): void
    {
    }

    /**
     * Run after the message acknowledged to queue server
     *
     * @param  string $status ack, reject, requeue
     * @return void
     */
    public function afterMessageAcknowledge(string $status): void
    {
    }

    /**
     * Reset processor object to default after execute
     *
     * @return bool
     */
    public function resetAfterProcess(): bool
    {
        return false;
    }
}
