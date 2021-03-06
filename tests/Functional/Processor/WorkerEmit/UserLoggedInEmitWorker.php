<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Processor\WorkerEmit;

use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Processors\Worker;

final class UserLoggedInEmitWorker extends Worker
{
    public static $receivedData;

    public function execute(Request $request): void
    {
        static::$receivedData = $request->getBody();
    }

    public function getQueueName(): string
    {
        return 'service_emit_result';
    }

    public function getJobName(): string
    {
        return 'user_logged_in_result';
    }

    public function durableQueue(): bool
    {
        return false;
    }
}
