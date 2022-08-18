<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Topic;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\RequestResponse\RequestTopic;
use Araz\MicroService\Processors\Topic;
// use Interop\Amqp\AmqpConsumer;
// use Interop\Amqp\Impl\AmqpMessage;

use Interop\Queue\Consumer as AmqpConsumer;
use Interop\Queue\Message as AmqpMessage;

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

    public function execute(RequestTopic $request): void
    {
        $this->events['routingKey'] = $request->getRoutingKey();
        $this->events['data'] = $request->getBody();
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

    public function beforeExecute($request): bool
    {
        $this->events['beforeExecute'] = $request->getBody();

        return true;
    }

    public function afterExecute($request): void
    {
        $this->events['afterExecute'] = $request->getBody();
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
            ->send()
        ;
    }
}
