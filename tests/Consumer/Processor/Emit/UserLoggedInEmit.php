<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Emit;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\Emit;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpMessage;

final class UserLoggedInEmit extends Emit
{
    private $events = [
        'id' => '',
        'data' => '',
        'process' => '',
        'beforeExecute' => '',
        'afterExecute' => '',
        'afterMessageAcknowledge' => '',
        'processorFinished' => '',
    ];

    public function execute(mixed $body): void
    {
        $this->events['data'] = $body;
    }

    public function getTopicName(): string
    {
        return 'user_logged_in';
    }

    public function getQueueName(): string
    {
        return sprintf('%s.user_logged_in_emit', $this->getQueue()->getAppName());
    }

    public function resetAfterProcess(): bool
    {
        return true;
    }

    public function durableQueue(): bool
    {
        return false;
    }

    public function process(AmqpMessage $message, AmqpConsumer $consumer): string
    {
        $this->events['id'] = $message->getMessageId();
        $this->events['process'] = Processor::ACK;
        return Processor::ACK;
    }

    public function beforeExecute(mixed $data): bool
    {
        $this->events['beforeExecute'] = $data;
        return true;
    }

    public function afterExecute(mixed $data): void
    {
        $this->events['afterExecute'] = $data;
    }

    public function afterMessageAcknowledge(string $status): void
    {
        $this->events['afterMessageAcknowledge'] = $status;
    }

    public function processorFinished(?string $result): void
    {
        $this->events['processorFinished'] = $result;

        $this->getQueue()->getClient()->worker()
            ->setQueueName('service_emit_result')
            ->setJobName('user_logged_in_result')
            ->setData($this->events)
            ->send();
    }
}
