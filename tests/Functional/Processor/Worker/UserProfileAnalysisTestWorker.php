<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Processor\Worker;

use Araz\MicroService\Processors\Worker;

final class UserProfileAnalysisTestWorker extends Worker
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
        return 'user_profile_analysis_result';
    }

    public function durableQueue(): bool
    {
        return false;
    }
}
