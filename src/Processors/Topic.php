<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\RequestResponse\RequestTopic;
use Araz\MicroService\Queue;

/**
 * @inheritDoc
 */
abstract class Topic extends Processor
{
    /**
     * Process received topic.
     *
     * @param RequestTopic $request received data
     */
    abstract public function execute(RequestTopic $request): void;

    /**
     * Get routing keys for bind to topic.
     */
    abstract public function getRoutingKeys(): array;

    /**
     * Get topic name.
     */
    abstract public function getTopicName(): string;

    /**
     * @inheritDoc
     */
    final public static function getType(): string
    {
        return Queue::METHOD_JOB_TOPIC;
    }

    /**
     * Set queue is durable.
     */
    public function durableQueue(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function validateProcessor(): void
    {
        if (!trim($this->getQueueName())) {
            throw new \LogicException(sprintf('Loading topics, Queue name is required: %s', get_called_class()));
        }

        if (!trim($this->getTopicName())) {
            throw new \LogicException(sprintf('Loading topics, Topic name is required: %s', get_called_class()));
        }

        if (!count($this->getRoutingKeys())) {
            throw new \LogicException(sprintf('Loading topics, Routing key is required: %s', get_called_class()));
        }
    }
}
