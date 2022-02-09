<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Processor\Topic;

use Araz\MicroService\Processors\Topic;

final class UserCreatedTopic extends Topic
{
    public static $receivedData;
    public function execute(string $routingKey, mixed $body): void
    {
        static::$receivedData = $body;
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
}
