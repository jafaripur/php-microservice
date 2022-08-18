<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\Exceptions\CommandRejectException;
use Araz\MicroService\Exceptions\CommandTimeoutException;
use Araz\MicroService\Exceptions\CorrelationInvalidException;
use Araz\MicroService\Exceptions\SerializerNotFoundException;
use Araz\MicroService\MessageProperty;
use Araz\MicroService\Processor;
use Araz\MicroService\Processors\RequestResponse\ResponseAsync;
use Araz\MicroService\Queue;
use Interop\Amqp\Impl\AmqpMessage;
// use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\AmqpQueue as AmqpQueue;
use Generator;
// use Interop\Amqp\AmqpConsumer;
use Interop\Queue\Consumer as AmqpConsumer;
use Interop\Queue\Message;

final class AsyncSender
{
    public const COMMAND_ASYNC_MESSAGE_TIMEOUT = 10000;

    public const COMMAND_MESSAGE_TIMEOUT = 10000;

    public const COMMAND_MESSAGE_EXPIRE_AFTER_SEND = 1000;

    private AmqpQueue $queueResponse;

    private AmqpConsumer $consumer;

    /**
     * @var array<string, AmqpMessage>
     */
    private array $messages = [];

    /**
     * Undocumented function.
     *
     * @param int $timeout timeout for all command related to this async as millisecond
     */
    public function __construct(
        private Queue $queue,
        private int $timeout = self::COMMAND_ASYNC_MESSAGE_TIMEOUT
    ) {
        if ($timeout < 1) {
            throw new \LogicException('Timeout should be more than 0');
        }

        $this->queueResponse = $this->queue->createTemporaryQueue();
        $this->consumer = $this->queue->createConsumer($this->queueResponse);
    }

    /**
     * Send command.
     *
     * @param string   $correlationId identify this command to detect response by this identify
     * @param int      $timeout       as millisecond
     * @param null|int $priority      0-5
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

        /**
         * @var AmqpMessage $message
         */
        $message = $this->queue->createMessage($data, false);
        MessageProperty::setQueue($message, $queueName);
        MessageProperty::setJob($message, $jobName);
        MessageProperty::setMethod($message, (string)$this->queue::METHOD_JOB_COMMAND);
        $message->setCorrelationId($correlationId);
        $message->setReplyTo($this->queueResponse->getQueueName());

        $this->queue->getContext()->createProducer()
            ->setPriority($priority ?: null)
            ->setTimeToLive($timeout + self::COMMAND_MESSAGE_EXPIRE_AFTER_SEND)
            ->send($queue, $message)
        ;

        $this->messages[$correlationId] = $message;

        return $this;
    }

    /**
     * Start receiving command responses.
     *
     * @throws CommandTimeoutException
     * @throws CommandRejectException
     * @throws CorrelationInvalidException
     * @throws SerializerNotFoundException
     *
     * @return Generator<string, ResponseAsync, 0|positive-int>
     */
    public function receive(): Generator
    {
        $listen = $this->listen();

        /**
         * @var AmqpMessage $reply
         */
        foreach ($listen as $reply) {
            /**
             * @var string $correlationId
             */
            $correlationId = $reply->getCorrelationId();

            if (Processor::REJECT == MessageProperty::getStatus($reply)) {
                $this->consumer->acknowledge($reply);

                yield $correlationId => new ResponseAsync(
                    Processor::REJECT,
                    null
                );

                continue;
            }

            if (!isset($this->messages[$correlationId])) {
                $this->consumer->reject($reply, false);

                throw new CorrelationInvalidException('Invalid data received!');
            }

            /**
             * @var null|string $serialize
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

            yield $correlationId => new ResponseAsync(
                Processor::ACK,
                $serializer->unserialize($reply->getBody())
            );
        }

        if ($listen->getReturn() != count($this->messages)) {
            throw new CommandTimeoutException('Command timeout');
        }

        return $listen->getReturn();
    }

    /**
     * @psalm-return Generator<int, AmqpMessage|Message, mixed, 0|positive-int>
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
                ++$count;
            }

            if ($count == $sentMessageCount) {
                break;
            }

            usleep(20000); // 20ms
        }

        return $count;
    }
}
