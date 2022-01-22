<?php

namespace Araz\MicroService\Tests\Functional;

use Araz\MicroService\Exceptions\CommandTimeoutException;
use Araz\MicroService\Processor;
use Araz\MicroService\Queue;
use PHPUnit\Framework\TestCase;

class CommandTest extends TestCase
{
    private ?Queue $queue = null;

    protected function setUp(): void
    {
        if (!$this->queue) {
            $this->queue = new Queue(
                'test-app',
                [
                    'dsn' => $_ENV['AMQP_DSN'],
                    'persisted' => false,
                ],
                null,
                null,
                true,
                true,
                [
                    \Araz\MicroService\Tests\Functional\Consumer\ConsumerCommand::class,
                ]
            );

            $this->queue->getConsumer()->consume(1);
        }
    }

    public function testQueueSendCommandTimeout()
    {
        try {
            $this->queue->getSender()->command('service_command', 'profile_info', ['id' => 123], 100);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(CommandTimeoutException::class, $th);
        }
    }

    public function testQueueSendAndReceiveAsyncCommand()
    {
        $data = [
            'command-1' => ['id' => 123],
            'command-2' => ['id' => 1234],
        ];

        $commands = $this->queue->getSender()->async(4000)
            ->command('service_command', 'profile_info', $data['command-1'], 'command-1', 2000)
            ->command('service_command', 'profile_info', $data['command-2'], 'command-2', 2000);

        $this->queue->getConsumer()->consume(50);

        foreach ($commands->receive() as $correlationId => $dataReceived) {
            $this->assertEquals($dataReceived['result'], $data[$correlationId]);
            $this->assertEquals($dataReceived['ack'], Processor::ACK);
        }
    }

    public function testQueueSendAndReceiveAsyncRejectCommand()
    {
        $data = [
            'command-1' => ['id' => 123],
            'command-2' => ['id' => 1234],
        ];

        $commands = $this->queue->getSender()->async(4000)
            ->command('service_command', 'profile_info_reject', $data['command-1'], 'command-1', 2000)
            ->command('service_command', 'profile_info_reject', $data['command-2'], 'command-2', 2000);

        $this->queue->getConsumer()->consume(50);

        foreach ($commands->receive() as $correlationId => $dataReceived) {
            $this->assertEquals($dataReceived['result'], null);
            $this->assertEquals($dataReceived['ack'], Processor::REJECT);
        }
    }
}
