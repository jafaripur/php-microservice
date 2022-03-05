<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Processor\WorkerCommand;

use Araz\MicroService\Processors\Worker;

final class UserProfileInfoCommandProcessorEventsWorker extends Worker
{
    
    public static $receivedData;

    public function execute(mixed $body): void
    {
        static::$receivedData = $body;    
    }

    public function getQueueName(): string
    {
        return 'service_worker_result';
    }

    public function getJobName(): string
    {
        return 'user_profile_info_event_processor';
    }

    public function durableQueue(): bool
    {
        return false;
    }
}
