<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Processor\WorkerTopic;

use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Processors\Worker;

final class UserLoggedInTopicWorker extends Worker
{
    public static $receivedData;

    public function execute(Request $request): void
    {
        static::$receivedData = $request->getBody();
    }

    public function getQueueName(): string
    {
        return 'user_changed_result';
    }

    public function getJobName(): string
    {
        return 'user_topic_create_result';
    }

    public function durableQueue(): bool
    {
        return false;
    }
}
