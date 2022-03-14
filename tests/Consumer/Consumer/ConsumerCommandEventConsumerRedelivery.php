<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Consumer;

use Araz\MicroService\Processor;
use Araz\MicroService\ProcessorConsumer;
use Generator;
//use Interop\Amqp\AmqpConsumer;
//use Interop\Amqp\Impl\AmqpMessage;
use Interop\Queue\Consumer as AmqpConsumer;
use Interop\Queue\Message as AmqpMessage;

final class ConsumerCommandEventConsumerRedelivery extends ProcessorConsumer
{
    private $events = [
        'messageReceived' => '',
        'afterMessageAcknowledge' => '',
        'processorFinished' => '',
        'messageRedelivered' => '',
    ];

    public function getConsumerIdentify(): string
    {
        return 'consumer-command-event-consumer-redelivery';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Consumer\Processor\Command\UserGetInfoCommandEventsProcessorConsumerRedelivery::class;
    }

    public function messageReceived(AmqpMessage $message, AmqpConsumer $consumer): void
    {
        $this->events['messageReceived'] = true;
    }

    public function afterMessageAcknowledge(Processor $processor, string $status, AmqpMessage $message, AmqpConsumer $consumer): void
    {
        $this->events['afterMessageAcknowledge'] = true;
    }

    public function messageRedelivered(AmqpMessage $message, AmqpConsumer $consumer): void
    {
        $this->events['messageRedelivered'] = true;
    }

    public function processorFinished(?string $status, Processor $processor): void
    {
        $this->events['processorFinished'] = $status;

        $this->getQueue()->getClient()->worker()
            ->setQueueName('service_worker_result')
            ->setJobName('user_profile_info_event_processor_consumer_redelivery')
            ->setData($this->events)
            ->send();
    }
}
