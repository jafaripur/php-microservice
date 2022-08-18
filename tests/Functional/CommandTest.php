<?php

namespace Araz\MicroService\Tests\Functional;

use Araz\MicroService\AmqpConnection;
use Araz\MicroService\Exceptions\CommandRejectException;
use Araz\MicroService\Exceptions\CommandTimeoutException;
use Araz\MicroService\Exceptions\SerializerNotFoundException;
use Araz\MicroService\Processor;
use Araz\MicroService\Processors\RequestResponse\Response;
use Araz\MicroService\Processors\RequestResponse\ResponseAsync;
use Araz\MicroService\Queue;
use Araz\MicroService\Serializers\PhpSerializer;
use Araz\MicroService\Tests\Functional\Processor\WorkerCommand\UserProfileInfoCommandProcessorConsumerEventsWorker;
use Araz\MicroService\Tests\Functional\Processor\WorkerCommand\UserProfileInfoCommandProcessorConsumerRedeliveryEventsWorker;
use Araz\MicroService\Tests\Functional\Processor\WorkerCommand\UserProfileInfoCommandProcessorEventsWorker;
use PHPUnit\Framework\TestCase;

class CommandTest extends TestCase
{
    private ?Queue $queue = null;

    protected function setUp(): void
    {
        if (!$this->queue) {
            $this->queue = new Queue(
                'test-app',
                new AmqpConnection([
                    'dsn' => $_ENV['AMQP_DSN'],
                    'persisted' => false,
                ]),
                null,
                null,
                true,
                true,
                [
                    \Araz\MicroService\Tests\Functional\Consumer\Command\ConsumerCommandWorkerResult::class,
                ]
            );

            $this->queue->getConsumer()->consume(1);
        }
    }

    public function testQueueSendCommandException()
    {
        try {
            $this->queue->getClient()->command()->send();
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Queue name is required', $th->getMessage());
        }

        try {
            $this->queue->getClient()->command()
                ->setJobName('test')
                ->send()
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Queue name is required', $th->getMessage());
        }

        try {
            $this->queue->getClient()->command()
                ->setQueueName('test')
                ->send()
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Job name is required', $th->getMessage());
        }

        try {
            $this->queue->getClient()->command()
                ->setTimeout(-1)
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Timeout should be more than', $th->getMessage());
        }

        try {
            $this->queue->getClient()->command()
                ->setPriority(200)
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Priority accept between 0', $th->getMessage());
        }
    }

    public function testQueueSendCommandTimeout()
    {
        try {
            $this->queue->getClient()->command()
                ->setQueueName('service_command')
                ->setJobName('profile_info_fake')
                ->setData(['id' => 123])
                ->setTimeout(20)
                ->send()
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(CommandTimeoutException::class, $th);
        }
    }

    public function testQueueSendAndReceiveCommand()
    {
        $result = $this->queue->getClient()->command()
            ->setData(['id' => 123])
            ->setQueueName('service_command')
            ->setJobName('profile_info')
            ->setTimeout(2000)
            ->setPriority(0)
            ->send()
        ;

        $this->assertEquals($result->getBody(), ['id' => 123]);
    }

    public function testQueueSendAndReceiveAsyncCommand()
    {
        $data = [
            'command-1' => ['id' => 123],
            'command-2' => ['id' => 1234],
        ];

        $commands = $this->queue->getClient()->async(4000)
            ->command('service_command', 'profile_info', $data['command-1'], 'command-1', 2000, 0)
            ->command('service_command', 'profile_info', $data['command-2'], 'command-2', 2000, 1)
        ;

        /**
         * @var ResponseAsync $response
         */
        foreach ($commands->receive() as $correlationId => $response) {
            $this->assertEquals($response->getBody(), $data[$correlationId]);
            $this->assertEquals($response->getAck(), Processor::ACK);
        }
    }

    public function testQueueSendAndReceiveRejectCommand()
    {
        try {
            $this->queue->getClient()->command()
                ->setQueueName('service_command')
                ->setJobName('profile_info_reject')
                ->setData(['id' => 123])
                ->setTimeout(2000)
                ->setPriority(5)
                ->send()
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(CommandRejectException::class, $th);
        }
    }

    public function testQueueSendAndReceiveAsyncRejectCommand()
    {
        $data = [
            'command-1' => ['id' => 123],
            'command-2' => ['id' => 1234],
        ];

        $commands = $this->queue->getClient()->async(4000)
            ->command('service_command', 'profile_info_reject', $data['command-1'], 'command-1', 2000)
            ->command('service_command', 'profile_info_reject', $data['command-2'], 'command-2', 2000)
        ;

        /**
         * @var ResponseAsync $response
         */
        foreach ($commands->receive() as $correlationId => $response) {
            $this->assertInstanceOf(ResponseAsync::class, $response);
            $this->assertEquals($response->getBody(), null);
            $this->assertEquals($response->getAck(), Processor::REJECT);
        }
    }

    public function testQueueSendCommandSerializeNotFound()
    {
        $this->queue->removeSerializer(PhpSerializer::class);

        try {
            $this->queue->getClient()->command()
                ->setQueueName('service_command')
                ->setJobName('profile_info_serializer')
                ->setData(['id' => 123])
                ->setTimeout(2000)
                ->setPriority(5)
                ->send()
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(SerializerNotFoundException::class, $th);
        }

        $this->queue->addSerializer(PhpSerializer::class);
    }

    public function testQueueSendAndReceiveEventsCommandProcessor()
    {
        $response = $this->queue->getClient()->command()
            ->setQueueName('service_command')
            ->setJobName('profile_info_command_events_processor')
            ->setData(['id' => 123])
            ->setTimeout(2000)
            ->setPriority(5)
            ->send()
        ;

        $this->assertInstanceOf(Response::class, $response);

        $this->assertEquals($response->getBody(), ['id' => 123]);

        $this->queue->getConsumer()->consume(50);

        $this->assertEquals(UserProfileInfoCommandProcessorEventsWorker::$receivedData, [
            'afterMessageReplytoCommand' => Processor::ACK,
            'process' => Processor::ACK,
            'beforeExecute' => ['id' => 123],
            'afterExecute' => ['id' => 123],
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);
    }

    public function testQueueSendAndReceiveEventsCommandProcessorConsumer()
    {
        $response = $this->queue->getClient()->command()
            ->setQueueName('service_command')
            ->setJobName('profile_info_command_events_processor_consumer')
            ->setData(['id' => 123])
            ->setTimeout(2000)
            ->setPriority(5)
            ->send()
        ;

        $this->assertInstanceOf(Response::class, $response);

        $this->assertEquals($response->getBody(), ['id' => 123]);

        $this->queue->getConsumer()->consume(50);

        $this->assertEquals(UserProfileInfoCommandProcessorConsumerEventsWorker::$receivedData, [
            'messageReceived' => true,
            'afterMessageAcknowledge' => true,
            'processorFinished' => Processor::ACK,
        ]);
    }

    public function testQueueSendAndReceiveEventsCommandProcessorConsumerRedelivery()
    {
        $response = $this->queue->getClient()->command()
            ->setQueueName('service_command')
            ->setJobName('profile_info_command_events_processor_consumer_redelivery')
            ->setData(['id' => 123])
            ->setTimeout(2000)
            ->setPriority(5)
            ->send()
        ;

        $this->assertInstanceOf(Response::class, $response);

        $this->assertEquals($response->getBody(), ['id' => 123]);

        $this->queue->getConsumer()->consume(50);

        $this->assertEquals(UserProfileInfoCommandProcessorConsumerRedeliveryEventsWorker::$receivedData, [
            'messageReceived' => true,
            'afterMessageAcknowledge' => true,
            'processorFinished' => Processor::REQUEUE,
            'messageRedelivered' => true,
        ]);
    }
}
