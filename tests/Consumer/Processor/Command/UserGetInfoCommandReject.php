<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Command;

use Araz\MicroService\Processors\Command;
use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Processors\RequestResponse\Response;
//use Interop\Amqp\AmqpConsumer;
//use Interop\Amqp\AmqpMessage;
use Interop\Queue\Consumer as AmqpConsumer;
use Interop\Queue\Message as AmqpMessage;

final class UserGetInfoCommandReject extends Command
{
    public function process(AmqpMessage $message, AmqpConsumer $consumer): string
    {
        return self::REJECT;
    }

    public function execute(Request $request): Response
    {
        return new Response($request->getBody());
    }

    public function getQueueName(): string
    {
        return 'service_command';
    }

    public function getJobName(): string
    {
        return 'profile_info_reject';
    }

    public function durableQueue(): bool
    {
        return false;
    }
}
