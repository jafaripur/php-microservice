<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors;

use Araz\MicroService\Processor;

/**
 * @inheritDoc
 */
abstract class Topic extends Processor
{
    /**
     * Process received topic
     *
     * @param  string $routingKey
     * @param  mixed $body received data
     * @return void
     */
    abstract public function execute(string $routingKey, mixed $body): void;

    /**
     * Get routing keys for bind to topic
     *
     * @return array
     */
    abstract public function getRoutingKeys(): array;

    /**
     * Get topic name
     *
     * @return string
     */
    abstract public function getTopicName(): string;

    /**
     * @inheritDoc
     */
    public function validateProcessor(): void
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

    /**
     * Set queue is durable
     *
     * @return bool
     *
     */
    public function durableQueue(): bool
    {
        return true;
    }
}
