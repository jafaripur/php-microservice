<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Processor\Emit;

use Araz\MicroService\Processors\Emit;

final class UserLoggedInEmit extends Emit
{
    public static $receivedData;
    public function execute(mixed $body): void
    {
        static::$receivedData = $body;
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
}
