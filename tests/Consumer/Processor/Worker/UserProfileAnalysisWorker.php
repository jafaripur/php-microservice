<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Worker;

use Araz\MicroService\Processor;
use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Processors\Worker;
// use Interop\Amqp\AmqpConsumer;
// use Interop\Amqp\Impl\AmqpMessage;

use Interop\Queue\Consumer as AmqpConsumer;
use Interop\Queue\Message as AmqpMessage;

final class UserProfileAnalysisWorker extends Worker
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

    public function getQueueName(): string
    {
        return 'service_worker';
    }

    public function getJobName(): string
    {
        return 'user_profile_analysis';
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
            ->setQueueName('service_worker_result')
            ->setJobName('user_profile_analysis_result')
            ->setData($this->events)
            ->setPriority(isset($this->events['data']['priority']) ? (int)$this->events['data']['priority'] : 0)
            ->setExpiration(isset($this->events['data']['expire']) ? (int)$this->events['data']['expire'] : 0)
            ->send()
        ;
    }
}
