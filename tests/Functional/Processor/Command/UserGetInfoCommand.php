<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Processor\Command;

use Araz\MicroService\Processors\Command;

final class UserGetInfoCommand extends Command
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
        return 'profile_info';
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
