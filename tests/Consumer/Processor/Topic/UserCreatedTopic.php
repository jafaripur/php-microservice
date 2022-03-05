<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Topic;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\Topic;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpMessage;

final class UserCreatedTopic extends Topic
{
    private $events = [
        'id' => '',
        'routingKey' => '',
        'data' => '',
        'process' => '',
        'beforeExecute' => '',
        'afterExecute' => '',
        'afterMessageAcknowledge' => '',
        'processorFinished' => '',
    ];

    public function execute(string $routingKey, mixed $body): void
    {
        $this->events['routingKey'] = $routingKey;
        $this->events['data'] = $body;
    }

    public function getTopicName(): string
    {
        return 'user_changed';
    }

    public function getRoutingKeys(): array
    {
        return [
            'user_topic_create',
            'user_topic_update',
        ];
    }

    public function getQueueName(): string
    {
        return sprintf('%s.user_created_topic', $this->getQueue()->getAppName());
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
            ->setQueueName('user_changed_result')
            ->setJobName('user_topic_create_result')
            ->setData($this->events)
            ->send();
    }
}
