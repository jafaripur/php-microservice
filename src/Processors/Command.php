<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Processors\RequestResponse\Response;
use Araz\MicroService\Queue;

/**
 * @inheritDoc
 */
abstract class Command extends Processor
{
    /**
     * Process received command.
     *
     * @param Request $request received data
     *
     * @return Response data which send to sender
     */
    abstract public function execute(Request $request): Response;

    /**
     * Command name to run.
     */
    abstract public function getJobName(): string;

    /**
     * Run after the message is command and when replied back command response.
     *
     * @param null|string $messageId     message id
     * @param null|string $replyId       message id of reply message
     * @param null|string $correlationId correlation id of message
     * @param string      $status        ack, reject, requeue
     */
    public function afterMessageReplytoCommand(?string $messageId, ?string $replyId, ?string $correlationId, string $status): void
    {
    }

    /**
     * @inheritDoc
     */
    final public static function getType(): string
    {
        return Queue::METHOD_JOB_COMMAND;
    }

    /**
     * @inheritDoc
     */
    protected function validateProcessor(): void
    {
        if (!trim($this->getQueueName())) {
            throw new \LogicException(sprintf('Loading commands, Queue name is required: %s', get_called_class()));
        }

        if (!trim($this->getJobName())) {
            throw new \LogicException(sprintf('Loading commands, Job name is required: %s', get_called_class()));
        }
    }
}
