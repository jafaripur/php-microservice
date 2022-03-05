<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Consumer;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerCommand extends ProcessorConsumer
{
    public function getConsumerIdentify(): string
    {
        return 'consumer-command';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Consumer\Processor\Command\UserGetInfoCommand::class;
        yield \Araz\MicroService\Tests\Consumer\Processor\Command\UserGetInfoCommandReject::class;
        yield \Araz\MicroService\Tests\Consumer\Processor\Command\UserGetInfoCommandSerializer::class;
        yield \Araz\MicroService\Tests\Consumer\Processor\Command\UserGetInfoCommandEventsProcessor::class;
    }
}
