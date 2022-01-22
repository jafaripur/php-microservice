<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Interop\Amqp\AmqpContext;
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
     * Get current running queue
     *
     * @return Queue
     */
    protected function getQueue(): Queue
    {
        return $this->queue;
    }

    /**
     * Get current running processor consumer
     *
     * @return ProcessorConsumer
     */
    protected function getProcessorConsumer(): ProcessorConsumer
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
     * @param AmqpContext $context
     *
     * @return string this can be self::ACK, self::REJECT, self::REQUEUE
     *
     */
    public function process(AmqpMessage $message, AmqpConsumer $consumer, AmqpContext $context): string
    {
        return self::ACK;
    }

    /**
     * Run before the main action
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
     * Run after the message is command and when replied back command response
     *
     * @param  string       $messageId   message id
     * @param  string       $replyId   message id of reply message
     * @param  string       $correlationId   correlation id of message
     * @param  string       $status ack, reject, requeue
     * @return void
     */
    public function afterMessageReplytoCommand(?string $messageId, ?string $replyId, ?string $correlationId, string $stauts): void
    {
    }
}
