<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Consumer\Emit;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerEmitWorkerResult extends ProcessorConsumer
{
    public function getConsumerIdentify(): string
    {
        return 'consumer-emit-worker';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Functional\Processor\WorkerEmit\UserLoggedInEmitWorker::class;
    }
}
