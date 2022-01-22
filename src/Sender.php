<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Araz\MicroService\Exceptions\CommandRejectException;
use Araz\MicroService\Exceptions\CommandTimeoutException;
use Araz\MicroService\Exceptions\CorrelationInvalidException;
use Araz\MicroService\Exceptions\SerializerNotFoundException;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\Impl\AmqpTopic;
use Araz\MicroService\Processor;

final class Sender
{
    public const COMMAND_MESSAGE_EXPIRE_AFTER_SEND = 1000;
    public const COMMAND_MESSAGE_TIMEOUT = 10000;

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
     * Send command
     *
     * @param  string       $queueName
     * @param  string       $jobName
     * @param  mixed        $data
     * @param  integer $timeout    as millisecond
     * @param  integer|null $priority  0-5
     * @return mixed result of command received
     *
     * @throws CommandTimeoutException
     * @throws CommandRejectException
     * @throws CorrelationInvalidException
     * @throws SerializerNotFoundException
     */
    public function command(string $queueName, string $jobName, mixed $data, int $timeout = self::COMMAND_MESSAGE_TIMEOUT, ?int $priority = null): mixed
    {
        if ($priority > $this->queue::MAX_PRIORITY || $priority < 0) {
            throw new \LogicException(sprintf('Priority accept between 0 and %s', $this->queue::MAX_PRIORITY));
        }

        if ($timeout < 1) {
            throw new \LogicException('Timeout should be more than 0');
        }

        $queueResponse = $this->queue->createTemporaryQueue();

        $consumer = $this->queue->createConsumer($queueResponse);


        $queue = $this->queue->createQueue($queueName, false);
        $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        $this->queue->declareQueue($queue);

        $message = $this->queue->createMessage($data, false);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_QUEUE, $queueName);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_JOB, $jobName);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_METHOD, $this->queue::METHOD_JOB_COMMAND);
        $message->setCorrelationId($this->queue->createUniqueIdentify());
        $message->setReplyTo($queueResponse->getQueueName());

        $this->queue->getContext()->createProducer()
            ->setPriority($priority)
            ->setTimeToLive($timeout + self::COMMAND_MESSAGE_EXPIRE_AFTER_SEND)
            ->send($queue, $message);

        /**
         * @var AmqpMessage|null $reply
         */
        $reply = $consumer->receive($timeout);

        if (($reply instanceof AmqpMessage) == false) {
            throw new CommandTimeoutException('Command timeout.');
        }

        if (MessageProperty::getProperty($reply, $this->queue::QUEUE_MESSAGE_PROPERTY_STATUS) == Processor::REJECT) {
            $consumer->acknowledge($reply);
            throw new CommandRejectException('Command rejected.');
        }

        if ($reply->getCorrelationId() != $message->getCorrelationId()) {
            $consumer->reject($reply, false);
            $this->queue->getLogger()->error('Command message identify not same as received message', [
                'sent' => $message->getProperties() + $message->getHeaders(),
                'receive' => $reply->getProperties() + $reply->getHeaders(),
            ]);
            throw new CorrelationInvalidException('Invalid data received!');
        }

        $serialize = MessageProperty::getProperty($reply, $this->queue::QUEUE_MESSAGE_PROPERTY_SERIALIZE, null);

        $serializer = $this->queue->getSerializer($serialize, true);

        if (!$serializer) {
            $consumer->reject($reply);
            $this->queue->getLogger()->error('Serialize not found in our system', [
                'current_serialize' => $this->queue->getSerializer()->getName(),
                'sent' => $message->getProperties() + $message->getHeaders(),
                'receive' => $reply->getProperties() + $reply->getHeaders(),
            ]);
            throw new SerializerNotFoundException('Serialize not found in our system');
        }

        $consumer->acknowledge($reply);

        return $serializer->unserialize($reply->getBody());
    }

    /**
     * Emit message to all consumer which subscribe to specific topic name
     *
     * @param  string       $topicName
     * @param  mixed        $data
     * @param  integer|null $delay as millisecond, 0 or null disable delay
     * @return string|null         message id
     */
    public function emit(string $topicName, mixed $data, ?int $delay = 0): string|null
    {
        if ($delay < 0) {
            throw new \LogicException('Delay can not less than 0');
        }

        $topic = $this->queue->createTopic($topicName);
        $topic->setType(AmqpTopic::TYPE_FANOUT);
        $topic->addFlag(AmqpTopic::FLAG_PASSIVE);
        $this->queue->declareTopic($topic);

        $queue = $this->queue->createTemporaryQueue();

        $this->queue->bind($topic, $queue);

        $message = $this->queue->createMessage($data);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_TOPIC, $topicName);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_METHOD, $this->queue::METHOD_JOB_EMIT);

        $this->queue->createProducer()
            ->setDeliveryDelay($delay)
            ->send($topic, $message);

        return $message->getMessageId();
    }

    /**
     * Emit message to all consumer which subscribe to specific topic name and routing keys
     *
     * @param  string       $topicName
     * @param  string       $routingKey
     * @param  mixed        $data
     * @param  integer|null $delay       $delay as milisecond, 0 or null disable delay
     * @return string|null     message id
     */
    public function topic(string $topicName, string $routingKey, mixed $data, ?int $delay = 0): string|null
    {
        if ($delay < 0) {
            throw new \LogicException('Delay can not less than 0');
        }

        $topic = $this->queue->createTopic($topicName);
        $topic->setType(AmqpTopic::TYPE_DIRECT);
        $topic->addFlag(AmqpTopic::FLAG_PASSIVE);
        $this->queue->declareTopic($topic);

        $queue = $this->queue->createTemporaryQueue();

        $this->queue->bind($topic, $queue, $routingKey);

        $message = $this->queue->createMessage($data);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_TOPIC, $topicName);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_METHOD, $this->queue::METHOD_JOB_TOPIC);
        $message->setRoutingKey($routingKey);

        $this->queue->createProducer()
            ->setDeliveryDelay($delay)
            ->send($topic, $message);

        return $message->getMessageId();
    }

    /**
     * Send message to workers
     * expiration and delay not work with together
     *
     * @param  string       $queueName
     * @param  string       $jobName
     * @param  mixed        $data
     * @param  integer|null $priority   0-5
     * @param  integer|null $expiration as millisecond, 0 or null disable expire of message
     * @param  integer|null $delay      as millisecond, 0 or null disable delay
     * @return string|null             message id
     */
    public function worker(string $queueName, string $jobName, mixed $data, ?int $priority = null, ?int $expiration = null, ?int $delay = null): string|null
    {
        if (!is_null($delay) && !is_null($expiration)) {
            throw new \LogicException('Just one of $delay or $expiration can be set');
        }

        if ($priority > $this->queue::MAX_PRIORITY || $priority < 0) {
            throw new \LogicException(sprintf('Priority accept between 0 and %s', $this->queue::MAX_PRIORITY));
        }

        if ($delay < 0) {
            throw new \LogicException('Delay can not less than 0');
        }

        $queue = $this->queue->createQueue($queueName);
        $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        $this->queue->declareQueue($queue);

        $message = $this->queue->createMessage($data);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_QUEUE, $queueName);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_JOB, $jobName);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_METHOD, $this->queue::METHOD_JOB_WORKER);

        $this->queue->createProducer()
            ->setPriority($priority)
            ->setTimeToLive($expiration)
            ->setDeliveryDelay($delay)
            ->send($queue, $message);

        return $message->getMessageId();
    }
}
