<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Consumer\Worker;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerWorkerResult extends ProcessorConsumer
{
    public function getConsumerIdentify(): string
    {
        return 'consumer-worker';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Functional\Processor\Worker\UserProfileAnalysisTestWorker::class;
    }
}
