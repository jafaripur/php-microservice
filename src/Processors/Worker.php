<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Queue;

/**
 * @inheritDoc
 */
abstract class Worker extends Processor
{
    /**
     * Process received worker.
     *
     * @param Request $request received data
     */
    abstract public function execute(Request $request): void;

    /**
     * Worker name to run.
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
            throw new \LogicException(sprintf('Loading workers, Queue name is required: %s', get_called_class()));
        }

        if (!trim($this->getJobName())) {
            throw new \LogicException(sprintf('Loading workers, Job name is required: %s', get_called_class()));
        }
    }
}
