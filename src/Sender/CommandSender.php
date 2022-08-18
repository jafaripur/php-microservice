<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\Exceptions\CommandRejectException;
use Araz\MicroService\Exceptions\CommandTimeoutException;
use Araz\MicroService\Exceptions\CorrelationInvalidException;
use Araz\MicroService\Exceptions\SerializerNotFoundException;
use Araz\MicroService\MessageProperty;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\Impl\AmqpQueue;
use Araz\MicroService\Processor;
use Araz\MicroService\Processors\RequestResponse\Response;

final class CommandSender extends SenderBase
{
    public const COMMAND_MESSAGE_EXPIRE_AFTER_SEND = 1000;

    public const COMMAND_MESSAGE_TIMEOUT = 10000;

    private string $queueName = '';

    private string $jobName = '';

    private mixed $data = null;

    private int $timeout = self::COMMAND_MESSAGE_TIMEOUT;

    private ?int $priority = null;

    /**
     * Queue name.
     */
    public function setQueueName(string $name): self
    {
        $new = clone $this;
        $new->queueName = $name;

        return $new;
    }

    /**
     * Job name.
     */
    public function setJobName(string $name): self
    {
        $new = clone $this;
        $new->jobName = $name;

        return $new;
    }

    /**
     * Set payload data.
     */
    public function setData(mixed $data): self
    {
        $new = clone $this;
        $new->data = $data;

        return $new;
    }

    /**
     * Add timeout for command.
     *
     * @param int $timeout as millisecond
     */
    public function setTimeout(int $timeout): self
    {
        if ($timeout < 1) {
            throw new \LogicException('Timeout should be more than 0');
        }

        $new = clone $this;
        $new->timeout = $timeout;

        return $new;
    }

    /**
     * Add priority to command.
     *
     * @param int $priority between 0-5
     */
    public function setPriority(int $priority): self
    {
        if ($priority > $this->queue::MAX_PRIORITY || $priority < 0) {
            throw new \LogicException(sprintf('Priority accept between 0 and %s', $this->queue::MAX_PRIORITY));
        }

        $new = clone $this;
        $new->priority = $priority;

        return $new;
    }

    /**
     * Send command.
     *
     * @throws CommandTimeoutException
     * @throws CommandRejectException
     * @throws CorrelationInvalidException
     * @throws SerializerNotFoundException
     */
    public function send(): Response
    {
        if (empty(trim($this->queueName))) {
            throw new \LogicException('Queue name is required!');
        }

        if (empty(trim($this->jobName))) {
            throw new \LogicException('Job name is required!');
        }

        $queueResponse = $this->queue->createTemporaryQueue();

        $consumer = $this->queue->createConsumer($queueResponse);

        $queue = $this->queue->createQueue($this->queueName, false);

        if ($this->getPassive()) {
            $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        }

        $this->queue->declareQueue($queue);

        $message = $this->queue->createMessage($this->data, false);
        MessageProperty::setQueue($message, $this->queueName);
        MessageProperty::setJob($message, $this->jobName);
        MessageProperty::setMethod($message, $this->queue::METHOD_JOB_COMMAND);
        $message->setCorrelationId($this->queue->createUniqueIdentify());
        $message->setReplyTo($queueResponse->getQueueName());

        $this->queue->getContext()->createProducer()
            ->setPriority($this->priority ?: null)
            ->setTimeToLive($this->timeout + self::COMMAND_MESSAGE_EXPIRE_AFTER_SEND)
            ->send($queue, $message)
        ;

        /**
         * @var null|AmqpMessage $reply
         */
        $reply = $consumer->receive($this->timeout);

        if (($reply instanceof AmqpMessage) == false) {
            throw new CommandTimeoutException('Command timeout.');
        }

        if (Processor::REJECT == MessageProperty::getStatus($reply)) {
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

        $serialize = MessageProperty::getSerializer($reply);

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

        return new Response(
            $serializer->unserialize($reply->getBody())
        );
    }
}
