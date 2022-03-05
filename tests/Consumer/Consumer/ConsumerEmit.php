<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Consumer;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerEmit extends ProcessorConsumer
{
    public function getConsumerIdentify(): string
    {
        return 'consumer-emit';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Consumer\Processor\Emit\UserLoggedInEmit::class;
    }
}
