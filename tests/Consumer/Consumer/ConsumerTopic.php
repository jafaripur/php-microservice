<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Consumer;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerTopic extends ProcessorConsumer
{
    public function getConsumerIdentify(): string
    {
        return 'consumer-topic';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Consumer\Processor\Topic\UserCreatedTopic::class;
    }
}
