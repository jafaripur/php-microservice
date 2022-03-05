<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Command;

use Araz\MicroService\Processors\Command;

final class UserGetInfoCommandEventsProcessorConsumer extends Command
{
    public function execute(mixed $body): mixed
    {
        return $body;
    }

    public function getQueueName(): string
    {
        return 'service_command';
    }

    public function getJobName(): string
    {
        return 'profile_info_command_events_processor_consumer';
    }

    public function resetAfterProcess(): bool
    {
        return true;
    }

    public function durableQueue(): bool
    {
        return false;
    }
}
