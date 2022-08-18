<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\MessageProperty;
use Interop\Amqp\Impl\AmqpQueue;

final class WorkerSender extends SenderBase
{
    private string $queueName = '';

    private string $jobName = '';

    private mixed $data = null;

    private ?int $priority = null;

    private ?int $expiration = null;

    private ?int $delay = null;

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
     * Add delay.
     *
     * @param int $delay as millisecond
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
     * Add expiration.
     *
     * @param int $expiration as millisecond
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
     * Add priority to worker.
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
     * Send to Worker.
     */
    public function send(): ?string
    {
        if (empty(trim($this->queueName))) {
            throw new \LogicException('Queue name is required!');
        }

        if (empty(trim($this->jobName))) {
            throw new \LogicException('Job name is required!');
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

        if ($this->getPassive()) {
            $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        }
        $this->queue->declareQueue($queue);

        $message = $this->queue->createMessage($this->data);
        MessageProperty::setQueue($message, $this->queueName);
        MessageProperty::setJob($message, $this->jobName);
        MessageProperty::setMethod($message, $this->queue::METHOD_JOB_WORKER);

        $this->queue->createProducer()
            ->setPriority($this->priority ?: null)
            ->setTimeToLive($this->expiration ?: null)
            ->setDeliveryDelay($this->delay ?: null)
            ->send($queue, $message)
        ;

        return $message->getMessageId();
    }
}
