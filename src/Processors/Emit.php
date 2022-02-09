<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors;

use Araz\MicroService\Processor;

/**
 * @inheritDoc
 */
abstract class Emit extends Processor
{
    /**
     * Process received emit
     *
     * @param  mixed $body received data
     * @return void
     */
    abstract public function execute(mixed $body): void;

    /**
     * Get topic name
     *
     * @param  mixed $body received data
     * @return string
     */
    abstract public function getTopicName(): string;

    /**
     * @inheritDoc
     */
    public function validateProcessor(): void
    {
        if (!trim($this->getQueueName())) {
            throw new \LogicException(sprintf('Loading emits, Queue name is required: %s', get_called_class()));
        }

        if (!trim($this->getTopicName())) {
            throw new \LogicException(sprintf('Loading emits, Topic name is required: %s', get_called_class()));
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
