<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\Exceptions\CommandRejectException;
use Araz\MicroService\Exceptions\CommandTimeoutException;
use Araz\MicroService\Exceptions\CorrelationInvalidException;
use Araz\MicroService\Exceptions\SerializerNotFoundException;
use Araz\MicroService\MessageProperty;
use Araz\MicroService\Processor;
use Araz\MicroService\Queue;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\Impl\AmqpQueue;
use Generator;
use Interop\Amqp\AmqpConsumer;

final class AsyncSender
{
    public const COMMAND_ASYNC_MESSAGE_TIMEOUT = 10000;
    public const COMMAND_MESSAGE_TIMEOUT = 10000;
    public const COMMAND_MESSAGE_EXPIRE_AFTER_SEND = 1000;

    /**
     *
     * @var Queue $queue
     */
    private Queue $queue;

    protected int $timeout;

    /**
     *
     * @var AmqpQueue
     */
    private AmqpQueue $queueResponse;

    /**
     *
     * @var AmqpConsumer
     */
    private AmqpConsumer $consumer;

    /**
     *
     * @var array<string, AmqpMessage>
     */
    private array $messages = [];

    /**
     * Undocumented function
     *
     * @param  Queue       $queue
     * @param  integer $timeout timeout for all command related to this async as millisecond
     */
    public function __construct(Queue $queue, int $timeout = self::COMMAND_ASYNC_MESSAGE_TIMEOUT)
    {
        if ($timeout < 1) {
            throw new \LogicException('Timeout should be more than 0');
        }

        $this->queue = $queue;
        $this->timeout = $timeout;

        $this->queueResponse = $this->queue->createTemporaryQueue();
        $this->consumer = $this->queue->createConsumer($this->queueResponse);
    }

    /**
     * Send command
     *
     * @param  string       $queueName
     * @param  string       $jobName
     * @param  mixed        $data
     * @param  string        $correlationId  identify this command to detect response by this identify
     * @param  integer $timeout    as millisecond
     * @param  integer|null $priority 0-5
     * @return self
     *
     */
    public function command(string $queueName, string $jobName, mixed $data, string $correlationId, int $timeout = self::COMMAND_MESSAGE_TIMEOUT, ?int $priority = null): self
    {
        if ($timeout < 1) {
            throw new \LogicException('Timeout should be more than 0');
        }

        if ($timeout > $this->timeout) {
            throw new \LogicException(sprintf('Command timeout %s cant great than AsyncSender timeout %s', $timeout, $this->timeout));
        }

        if ($priority > $this->queue::MAX_PRIORITY || $priority < 0) {
            throw new \LogicException(sprintf('Priority accept between 0 and %s', (int)$this->queue::MAX_PRIORITY));
        }

        if (strlen(trim($correlationId)) > 100) {
            throw new \LogicException('CorrelationId should be less than 100 character.');
        }

        if (empty(trim($correlationId))) {
            throw new \LogicException('CorrelationId required for async command sending.');
        }

        $queue = $this->queue->createQueue($queueName, false);

        $message = $this->queue->createMessage($data, false);
        MessageProperty::setQueue($message, $queueName);
        MessageProperty::setJob($message, $jobName);
        MessageProperty::setMethod($message, (string)$this->queue::METHOD_JOB_COMMAND);
        $message->setCorrelationId($correlationId);
        $message->setReplyTo($this->queueResponse->getQueueName());

        $this->queue->getContext()->createProducer()
            ->setPriority($priority ?: null)
            ->setTimeToLive($timeout + self::COMMAND_MESSAGE_EXPIRE_AFTER_SEND)
            ->send($queue, $message);

        $this->messages[$correlationId] = $message;

        return $this;
    }

    /**
     * Start receiving command responses
     *
     * @return Generator
     *
     * @throws CommandTimeoutException
     * @throws CommandRejectException
     * @throws CorrelationInvalidException
     * @throws SerializerNotFoundException
     *
     */
    public function receive(): Generator
    {
        $listen = $this->listen();
        /**
         * @var AmqpMessage $reply
         */
        foreach ($listen as $reply) {
            $correlationId = $reply->getCorrelationId();

            if (MessageProperty::getStatus($reply) == Processor::REJECT) {
                $this->consumer->acknowledge($reply);
                yield $correlationId => [
                    'ack' => Processor::REJECT,
                    'result' => null,
                ];
                continue;
            }

            if (!isset($this->messages[$correlationId])) {
                $this->consumer->reject($reply, false);
                throw new CorrelationInvalidException('Invalid data received!');
            }

            /**
             * @var string|null $serialize
             */
            $serialize = MessageProperty::getSerializer($this->messages[$correlationId]);

            $serializer = $this->queue->getSerializer($serialize, true);

            if (!$serializer) {
                $this->consumer->reject($this->messages[$correlationId], false);
                $this->queue->getLogger()->error('Serialize not found in our system', [
                    'current_serialize' => $this->queue->getSerializer()->getName(),
                    'sent' => $this->messages[$correlationId]->getProperties() + $this->messages[$correlationId]->getHeaders(),
                    'receive' => $reply->getProperties() + $reply->getHeaders(),
                ]);
                throw new SerializerNotFoundException('Serialize not found in our system');
            }

            $this->consumer->acknowledge($reply);

            yield $correlationId => [
                'ack' => Processor::ACK,
                'result' => $serializer->unserialize($reply->getBody()),
            ];
        }

        if ($listen->getReturn() != count($this->messages)) {
            throw new CommandTimeoutException('Command timeout');
        }

        return $listen->getReturn();
    }

    /**
     * @psalm-return Generator<int, \Interop\Amqp\AmqpMessage, mixed, 0|positive-int>
     */
    private function listen(): Generator
    {
        $end = microtime(true) + ($this->timeout / 1000);

        $count = 0;

        $sentMessageCount = count($this->messages);

        while (microtime(true) < $end) {
            if ($message = $this->consumer->receiveNoWait()) {
                if (!isset($this->messages[$message->getCorrelationId()])) {
                    $this->consumer->reject($message);
                    $this->queue->getLogger()->error('Received async command correlation is invalid.', [
                        'received' => $message->getProperties() + $message->getHeaders(),
                    ]);
                    throw new CorrelationInvalidException('Invalid data received!');
                }

                yield $message;
                $count++;
            }

            if ($count == $sentMessageCount) {
                break;
            }

            usleep(20000); //20ms
        }

        return $count;
    }
}
