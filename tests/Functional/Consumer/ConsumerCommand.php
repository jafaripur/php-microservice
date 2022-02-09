<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Consumer;

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
        yield \Araz\MicroService\Tests\Functional\Processor\Command\UserGetInfoCommand::class;
        yield \Araz\MicroService\Tests\Functional\Processor\Command\UserGetInfoCommandReject::class;
    }
}
