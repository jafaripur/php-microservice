<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Functional\Processor\Command;

use Araz\MicroService\Processors\Command;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;

final class UserGetInfoCommandReject extends Command
{
    public function process(AmqpMessage $message, AmqpConsumer $consumer, AmqpContext $context): string
    {
        return self::REJECT;
    }

    public function execute(mixed $body): mixed
    {
        return $body;
    }

    public function getQueueName(): string
    {
        return 'service_command';
    }

    public function getJobName(): string
    {
        return 'profile_info_reject';
    }
}
