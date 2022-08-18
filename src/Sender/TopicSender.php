<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\MessageProperty;
use Interop\Amqp\Impl\AmqpTopic;
use Interop\Amqp\Impl\AmqpMessage;

final class TopicSender extends SenderBase
{
    private string $topicName = '';

    private string $routingKey = '';

    private mixed $data = null;

    private ?int $delay = null;

    /**
     * Topic name.
     */
    public function setTopicName(string $name): self
    {
        $new = clone $this;
        $new->topicName = $name;

        return $new;
    }

    /**
     * Routing key name.
     */
    public function setRoutingKey(string $name): self
    {
        $new = clone $this;
        $new->routingKey = $name;

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
     * Emit message to all consumer which subscribe to specific topic name and routing keys.
     *
     * @return null|string message id
     */
    public function send(): ?string
    {
        if (empty(trim($this->topicName))) {
            throw new \LogicException('Topic name is required!');
        }

        if (empty(trim($this->routingKey))) {
            throw new \LogicException('Routing key name is required!');
        }

        $topic = $this->queue->createTopic($this->topicName);
        $topic->setType(AmqpTopic::TYPE_DIRECT);

        if ($this->getPassive()) {
            $topic->addFlag(AmqpTopic::FLAG_PASSIVE);
        }

        $this->queue->declareTopic($topic);

        $queue = $this->queue->createTemporaryQueue();

        $this->queue->bind($topic, $queue, $this->routingKey);

        /**
         * @var AmqpMessage $message
         */
        $message = $this->queue->createMessage($this->data);
        MessageProperty::setTopic($message, $this->topicName);
        MessageProperty::setMethod($message, $this->queue::METHOD_JOB_TOPIC);
        $message->setRoutingKey($this->routingKey);

        $this->queue->createProducer()
            ->setDeliveryDelay($this->delay ?: null)
            ->send($topic, $message)
        ;

        return $message->getMessageId();
    }
}
