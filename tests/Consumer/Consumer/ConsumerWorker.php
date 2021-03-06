<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Consumer;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerWorker extends ProcessorConsumer
{
    public function getConsumerIdentify(): string
    {
        return 'consumer-worker';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Consumer\Processor\Worker\UserProfileAnalysisWorker::class;
    }
}
