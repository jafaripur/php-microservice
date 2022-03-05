<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors;

use Araz\MicroService\Processor;
use Araz\MicroService\Queue;

/**
 * @inheritDoc
 */
abstract class Worker extends Processor
{
    /**
     * Process received worker
     *
     * @param  mixed $body received data
     * @return void
     */
    abstract public function execute(mixed $body): void;

    /**
     * Worker name to run
     *
     * @return string
     */
    abstract public function getJobName(): string;

    /**
     * @inheritDoc
     */
    final public static function getType(): string
    {
        return Queue::METHOD_JOB_WORKER;
    }

    /**
     * @inheritDoc
     */
    protected function validateProcessor(): void
    {
        if (!trim($this->getQueueName())) {
            throw new \LogicException(sprintf('Loading workers, Queue name is required: %s', get_called_class()));
        }

        if (!trim($this->getJobName())) {
            throw new \LogicException(sprintf('Loading workers, Job name is required: %s', get_called_class()));
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
