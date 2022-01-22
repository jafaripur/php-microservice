<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Processor\Worker;

use Araz\MicroService\Processors\Worker;

final class UserProfileAnalysisWorker extends Worker
{
    public static $receivedData;
    public function execute(mixed $body): void
    {
        static::$receivedData = $body;
    }

    public function getQueueName(): string
    {
        return 'service_worker';
    }

    public function getJobName(): string
    {
        return 'user_profile_analysis';
    }
}
