<?php

declare(strict_types=1);

namespace Araz\MicroService\Sender;

use Araz\MicroService\MessageProperty;
use Interop\Amqp\Impl\AmqpTopic;

final class EmitSender extends SenderBase
{
    private string $topicName = '';

    private mixed $data;

    private ?int $delay = null;

    /**
     * Topic name
     *
     * @param  string $name
     * @return self
     */
    public function setTopicName(string $name): self
    {
        $new = clone $this;
        $new->topicName = $name;
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
     * Emit message to all consumer which subscribe to specific topic name
     *
     * @return mixed         message id
     */
    public function send(): mixed
    {
        if (empty(trim($this->topicName))) {
            throw new \LogicException("Topic name is required!");
        }

        $topic = $this->queue->createTopic($this->topicName);
        $topic->setType(AmqpTopic::TYPE_FANOUT);
        $topic->addFlag(AmqpTopic::FLAG_PASSIVE);
        $this->queue->declareTopic($topic);

        $queue = $this->queue->createTemporaryQueue();

        $this->queue->bind($topic, $queue);

        $message = $this->queue->createMessage($this->data);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_TOPIC, $this->topicName);
        MessageProperty::setProperty($message, $this->queue::QUEUE_MESSAGE_PROPERTY_METHOD, $this->queue::METHOD_JOB_EMIT);

        $this->queue->createProducer()
            ->setDeliveryDelay($this->delay)
            ->send($topic, $message);

        return $message->getMessageId();
    }
}
