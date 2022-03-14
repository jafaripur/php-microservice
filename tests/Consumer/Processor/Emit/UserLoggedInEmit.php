<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Emit;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\Emit;
use Araz\MicroService\Processors\RequestResponse\Request;
//use Interop\Amqp\AmqpConsumer;
//use Interop\Amqp\Impl\AmqpMessage;

use Interop\Queue\Consumer as AmqpConsumer;
use Interop\Queue\Message as AmqpMessage;

final class UserLoggedInEmit extends Emit
{
    private $events = [
        'id' => '',
        'data' => '',
        'process' => '',
        'beforeExecute' => '',
        'afterExecute' => '',
        'afterMessageAcknowledge' => '',
        'processorFinished' => '',
    ];

    public function execute(Request $request): void
    {
        $this->events['data'] = $request->getBody();
    }

    public function getTopicName(): string
    {
        return 'user_logged_in';
    }

    public function getQueueName(): string
    {
        return sprintf('%s.user_logged_in_emit', $this->getQueue()->getAppName());
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
        $this->events['id'] = $message->getMessageId();
        $this->events['process'] = Processor::ACK;
        return Processor::ACK;
    }

    public function beforeExecute(Request $request): bool
    {
        $this->events['beforeExecute'] = $request->getBody();
        return true;
    }

    public function afterExecute(Request $request): void
    {
        $this->events['afterExecute'] = $request->getBody();
    }

    public function afterMessageAcknowledge(string $status): void
    {
        $this->events['afterMessageAcknowledge'] = $status;
    }

    public function processorFinished(?string $result): void
    {
        $this->events['processorFinished'] = $result;

        $this->getQueue()->getClient()->worker()
            ->setQueueName('service_emit_result')
            ->setJobName('user_logged_in_result')
            ->setData($this->events)
            ->send();
    }
}
