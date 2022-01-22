<?php

declare(strict_types=1);

namespace Araz\MicroService\Tools;

use Araz\MicroService\Queue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\Impl\AmqpTopic;
use Interop\Queue\Exception\InvalidDestinationException;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy as RabbitMqDlxDelayStrategyMain;

class RabbitMqDlxDelayStrategy extends RabbitMqDlxDelayStrategyMain
{
    private const QUEUE_AUTO_DELETE_SECOND = 60000; // 60 second

    private Queue $queue;

    /**
     * $queue oject
     *
     * @param  Queue $queue
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function delayMessage(AmqpContext $context, AmqpDestination $dest, AmqpMessage $message, int $delay): void
    {
        /**
         * @var AmqpMessage $delayMessage
         */
        $delayMessage = $context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $delayMessage->setRoutingKey($message->getRoutingKey());

        if ($dest instanceof AmqpTopic) {
            $delayQueue = $this->queue->createQueue(uniqid('delayed.topic.', true));
            $delayQueue->addFlag(AmqpTopic::FLAG_DURABLE);
            //$delayQueue->addFlag(AmqpQueue::FLAG_AUTODELETE);
            $delayQueue->setArgument('x-message-ttl', $delay);
            $delayQueue->setArgument('x-expires', $delay + self::QUEUE_AUTO_DELETE_SECOND);
            $delayQueue->setArgument('x-dead-letter-exchange', $dest->getTopicName());
            $delayQueue->setArgument('x-dead-letter-routing-key', (string) $delayMessage->getRoutingKey());
        } elseif ($dest instanceof AmqpQueue) {
            $delayQueue = $this->queue->createQueue(uniqid('delayed.queue.', true));
            $delayQueue->addFlag(AmqpTopic::FLAG_DURABLE);
            //$delayQueue->addFlag(AmqpTopic::FLAG_AUTODELETE);
            $delayQueue->setArgument('x-message-ttl', $delay);
            $delayQueue->setArgument('x-expires', $delay + self::QUEUE_AUTO_DELETE_SECOND);
            $delayQueue->setArgument('x-dead-letter-exchange', '');
            $delayQueue->setArgument('x-dead-letter-routing-key', $dest->getQueueName());
        } else {
            throw new InvalidDestinationException(sprintf(
                'The destination must be an instance of %s but got %s.',
                AmqpTopic::class.'|'.AmqpQueue::class,
                get_class($dest)
            ));
        }
        $this->queue->declareQueue($delayQueue);
        $this->queue->createProducer()->send($delayQueue, $delayMessage);
    }
}
