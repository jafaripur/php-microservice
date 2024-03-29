<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Queue;

/**
 * @inheritDoc
 */
abstract class Emit extends Processor
{
    /**
     * Process received emit.
     *
     * @param Request $request received data
     */
    abstract public function execute(Request $request): void;

    /**
     * Get topic name.
     *
     * @param mixed $body received data
     */
    abstract public function getTopicName(): string;

    /**
     * @inheritDoc
     */
    final public static function getType(): string
    {
        return Queue::METHOD_JOB_EMIT;
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
            throw new \LogicException(sprintf('Loading emits, Queue name is required: %s', get_called_class()));
        }

        if (!trim($this->getTopicName())) {
            throw new \LogicException(sprintf('Loading emits, Topic name is required: %s', get_called_class()));
        }
    }
}
