<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\MessageProperty;
use Interop\Amqp\Impl\AmqpQueue;

final class WorkerSender extends SenderBase
{
    private string $queueName = '';

    private string $jobName = '';

    private mixed $data;

    private ?int $priority = null;

    private ?int $expiration = null;

    private ?int $delay = null;

    /**
     * Queue name
     *
     * @param  string $name
     * @return self
     */
    public function setQueueName(string $name): self
    {
        $new = clone $this;
        $new->queueName = $name;
        return $new;
    }

    /**
     * Job name
     *
     * @param  string $name
     * @return self
     */
    public function setJobName(string $name): self
    {
        $new = clone $this;
        $new->jobName = $name;
        return $new;
    }

    /**
     * Set payload data
     *
     * @param  mixed $data
     * @return self
     */
    public function setData(mixed $data): self
    {
        $new = clone $this;
        $new->data = $data;
        return $new;
    }

    /**
     * Add delay
     *
     * @param  integer $delay as millisecond
     * @return self
     */
    public function setDelay(int $delay): self
    {
        if ($delay < 0) {
            throw new \LogicException('Delay can not less than 0');
        }

        $new = clone $this;
        $new->delay = $delay;
        return $new;
    }

    /**
     * Add expiration
     *
     * @param  integer $expiration as millisecond
     * @return self
     */
    public function setExpiration(int $expiration): self
    {
        if ($expiration < 0) {
            throw new \LogicException('Expiration can not less than 0');
        }

        $new = clone $this;
        $new->expiration = $expiration;
        return $new;
    }

    /**
     * Add priority to worker
     *
     * @param  integer $priority between 0-5
     * @return self
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
     * Send to Worker
     *
     * @return mixed
     */
    public function send(): mixed
    {
        if (empty(trim($this->queueName))) {
            throw new \LogicException("Queue name is required!");
        }

        if (empty(trim($this->jobName))) {
            throw new \LogicException("Job name is required!");
        }

        if ($this->delay && $this->expiration) {
            throw new \LogicException('Just one of $delay or $expiration can be set');
        }

        if ($this->priority > $this->queue::MAX_PRIORITY || $this->priority < 0) {
            throw new \LogicException(sprintf('Priority accept between 0 and %s', $this->queue::MAX_PRIORITY));
        }

        if ($this->delay < 0) {
            throw new \LogicException('Delay can not less than 0');
        }

        $queue = $this->queue->createQueue($this->queueName);
        $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        $this->queue->declareQueue($queue);

        $message = $this->queue->createMessage($this->data);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_QUEUE, $this->queueName);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_JOB, $this->jobName);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_METHOD, $this->queue::METHOD_JOB_WORKER);

        $this->queue->createProducer()
            ->setPriority($this->priority)
            ->setTimeToLive($this->expiration)
            ->setDeliveryDelay($this->delay)
            ->send($queue, $message);

        return $message->getMessageId();
    }
}
