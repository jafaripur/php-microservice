<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Consumer\Topic;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerTopicWorkerResult extends ProcessorConsumer
{
    public function getConsumerIdentify(): string
    {
        return 'consumer-topic-worker';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Functional\Processor\WorkerTopic\UserLoggedInTopicWorker::class;
    }
}
