<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors;

use Araz\MicroService\Processor;

/**
 * @inheritDoc
 */
abstract class Command extends Processor
{
    /**
     * Process received command
     *
     * @param  mixed $body received data
     * @return mixed data which send to sender
     */
    abstract public function execute(mixed $body): mixed;

    /**
     * Command name to run
     *
     * @return string
     */
    abstract public function getJobName(): string;

    /**
     * @inheritDoc
     */
    public function validateProcessor(): void
    {
        if (!trim($this->getQueueName())) {
            throw new \LogicException(sprintf('Loading commands, Queue name is required: %s', get_called_class()));
        }

        if (!trim($this->getJobName())) {
            throw new \LogicException(sprintf('Loading commands, Job name is required: %s', get_called_class()));
        }
    }
}
