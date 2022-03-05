<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Command;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\Command;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpMessage;

final class UserGetInfoCommandEventsProcessor extends Command
{

    private $events = [
        'afterMessageReplytoCommand' => '',
        'process' => '',
        'beforeExecute' => '',
        'afterExecute' => '',
        'afterMessageAcknowledge' => '',
        'processorFinished' => '',
    ];
    
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
        return 'profile_info_command_events_processor';
    }

    public function resetAfterProcess(): bool
    {
        return true;
    }

    public function durableQueue(): bool
    {
        return false;
    }

    public function afterMessageReplytoCommand(?string $messageId, ?string $replyId, ?string $correlationId, string $status): void
    {
        $this->events['afterMessageReplytoCommand'] = $status;
    }

    public function process(AmqpMessage $message, AmqpConsumer $consumer): string
    {
        $this->events['process'] = Processor::ACK;
        return Processor::ACK;
    }

    public function beforeExecute(mixed $data): bool
    {
        $this->events['beforeExecute'] = $data;
        return true;
    }

    public function afterExecute(mixed $data): void
    {
        $this->events['afterExecute'] = $data;
    }

    public function afterMessageAcknowledge(string $status): void
    {
        $this->events['afterMessageAcknowledge'] = $status;
    }

    public function processorFinished(?string $result): void
    {
        $this->events['processorFinished'] = $result;

        $this->getQueue()->getClient()->worker()
            ->setQueueName('service_worker_result')
            ->setJobName('user_profile_info_event_processor')
            ->setData($this->events)
            ->send();
    }
}
