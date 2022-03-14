<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Command;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\Command;
use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Processors\RequestResponse\Response;
//use Interop\Amqp\AmqpConsumer;
//use Interop\Amqp\Impl\AmqpMessage;
use Interop\Queue\Consumer as AmqpConsumer;
use Interop\Queue\Message as AmqpMessage;

final class UserGetInfoCommandEventsProcessorConsumerRedelivery extends Command
{
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
        return 'profile_info_command_events_processor_consumer_redelivery';
    }

    public function resetAfterProcess(): bool
    {
        return true;
    }

    public function durableQueue(): bool
    {
        return false;
    }

    public function process(AmqpMessage $message, AmqpConsumer $consumer): string
    {
        return Processor::REQUEUE;
    }
}
