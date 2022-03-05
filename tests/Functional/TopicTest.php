<?php

namespace Araz\MicroService\Tests\Functional;

use Araz\MicroService\Processor;
use Araz\MicroService\Queue;
use Araz\MicroService\Tests\Functional\Processor\WorkerTopic\UserLoggedInTopicWorker;
use Araz\MicroService\Tests\Mock\Processor\Topic\UserCreatedTopic;
use PHPUnit\Framework\TestCase;

class TopicTest extends TestCase
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
                    \Araz\MicroService\Tests\Functional\Consumer\Topic\ConsumerTopicWorkerResult::class,
                ]
            );

            $this->queue->getConsumer()->consume(1);
        }
    }

    public function testQueueSendTopicException()
    {
        try {
            $this->queue->getClient()->topic()->send();
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Topic name is required', $th->getMessage());
        }

        try {
            $this->queue->getClient()->topic()
                ->setTopicName('test')
                ->send();
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Routing key name is required', $th->getMessage());
        }

        try {
            $this->queue->getClient()->topic()
                ->setRoutingKey('test')
                ->send();
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Topic name is required', $th->getMessage());
        }

        try {
            $this->queue->getClient()->topic()->setDelay(-1);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Delay can not less than 0', $th->getMessage());
        }
    }

    public function testQueueSendAndReceiveTopic()
    {
        $data = ['id' => 123];

        $id = $this->queue->getClient()->topic()
            ->setTopicName('user_changed')
            ->setRoutingKey('user_topic_create')
            ->setData($data)
            ->send();

        $this->queue->getConsumer()->consume(50);
        
        $this->assertEquals(UserLoggedInTopicWorker::$receivedData, [
            'id' => $id,
            'routingKey' => 'user_topic_create',
            'data' => $data,
            'process' => Processor::ACK,
            'beforeExecute' => $data,
            'afterExecute' => $data,
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);

        $data = ['id' => 1234];

        $id = $this->queue->getClient()->topic()
            ->setTopicName('user_changed')
            ->setRoutingKey('user_topic_update')
            ->setData($data)
            ->send();

        $this->queue->getConsumer()->consume(50);

        $this->assertEquals(UserLoggedInTopicWorker::$receivedData, [
            'id' => $id,
            'routingKey' => 'user_topic_update',
            'data' => $data,
            'process' => Processor::ACK,
            'beforeExecute' => $data,
            'afterExecute' => $data,
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);

    }

    public function testQueueSendAndReceiveTopicDelay()
    {
        $data = ['id' => 123];

        UserLoggedInTopicWorker::$receivedData = null;

        $id = $this->queue->getClient()->topic()
            ->setTopicName('user_changed')
            ->setRoutingKey('user_topic_create')
            ->setData($data)
            ->setDelay(120)
            ->send();

        $this->queue->getConsumer()->consume(50);
        $this->assertEquals(UserLoggedInTopicWorker::$receivedData, null);

        $this->queue->getConsumer()->consume(80);
        $this->assertEquals(UserLoggedInTopicWorker::$receivedData, [
            'id' => $id,
            'routingKey' => 'user_topic_create',
            'data' => $data,
            'process' => Processor::ACK,
            'beforeExecute' => $data,
            'afterExecute' => $data,
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);

        UserLoggedInTopicWorker::$receivedData = null;
        $id = $this->queue->getClient()->topic()
            ->setTopicName('user_changed')
            ->setRoutingKey('user_topic_update')
            ->setData($data)
            ->setDelay(120)
            ->send();

        $this->queue->getConsumer()->consume(50);
        $this->assertEquals(UserLoggedInTopicWorker::$receivedData, null);
        $this->queue->getConsumer()->consume(80);
        $this->assertEquals(UserLoggedInTopicWorker::$receivedData, [
            'id' => $id,
            'routingKey' => 'user_topic_update',
            'data' => $data,
            'process' => Processor::ACK,
            'beforeExecute' => $data,
            'afterExecute' => $data,
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);
    }
}
