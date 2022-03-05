<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Consumer;

use Araz\MicroService\Processor;
use Araz\MicroService\ProcessorConsumer;
use Generator;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpMessage;

final class ConsumerCommandEventConsumer extends ProcessorConsumer
{
    private $events = [
        'messageReceived' => '',
        'afterMessageAcknowledge' => '',
        'processorFinished' => '',
    ];

    public function getConsumerIdentify(): string
    {
        return 'consumer-command-event-consumer';
    }

    public function getProcessors(): Generator
    {
        yield \Araz\MicroService\Tests\Consumer\Processor\Command\UserGetInfoCommandEventsProcessorConsumer::class;
    }

    public function messageReceived(AmqpMessage $message, AmqpConsumer $consumer): void
    {
        $this->events['messageReceived'] = true;
    }

    public function afterMessageAcknowledge(Processor $processor, string $status, AmqpMessage $message, AmqpConsumer $consumer): void
    {
        $this->events['afterMessageAcknowledge'] = true;
    }

    public function processorFinished(?string $status, Processor $processor): void
    {
        $this->events['processorFinished'] = $status;

        $this->getQueue()->getClient()->worker()
            ->setQueueName('service_worker_result')
            ->setJobName('user_profile_info_event_processor_consumer')
            ->setData($this->events)
            ->send();
    }
}
