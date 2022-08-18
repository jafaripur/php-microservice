<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Consumer\Command;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerCommandWorkerResult extends ProcessorConsumer
{
    public function getConsumerIdentify(): string
    {
        return 'consumer-command-worker';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Functional\Processor\WorkerCommand\UserProfileInfoCommandProcessorEventsWorker::class;

        yield \Araz\MicroService\Tests\Functional\Processor\WorkerCommand\UserProfileInfoCommandProcessorConsumerEventsWorker::class;

        yield \Araz\MicroService\Tests\Functional\Processor\WorkerCommand\UserProfileInfoCommandProcessorConsumerRedeliveryEventsWorker::class;
    }
}
