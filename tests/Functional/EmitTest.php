<?php

namespace Araz\MicroService\Tests\Functional;

use Araz\MicroService\AmqpConnection;
use Araz\MicroService\Processor;
use Araz\MicroService\Queue;
use Araz\MicroService\Tests\Functional\Processor\WorkerEmit\UserLoggedInEmitWorker;
use PHPUnit\Framework\TestCase;

class EmitTest extends TestCase
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
                    \Araz\MicroService\Tests\Functional\Consumer\Emit\ConsumerEmitWorkerResult::class,
                ]
            );

            $this->queue->getConsumer()->consume(1);
        }
    }

    public function testQueueSendEmitException()
    {
        try {
            $this->queue->getClient()->emit()->send();
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Topic name is required', $th->getMessage());
        }

        try {
            $this->queue->getClient()->emit()->setDelay(-1);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Delay can not less than 0', $th->getMessage());
        }
    }

    public function testQueueSendAndReceiveEmit()
    {
        $data = ['id' => 123];

        $id = $this->queue->getClient()->emit()
            ->setTopicName('user_logged_in')
            ->setData($data)
            ->send();

        $this->queue->getConsumer()->consume(50);

        $this->assertEquals(UserLoggedInEmitWorker::$receivedData, [
            'id' => $id,
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

        UserLoggedInEmitWorker::$receivedData = null;

        $id = $this->queue->getClient()->emit()
            ->setTopicName('user_logged_in')
            ->setData($data)
            ->setDelay(100)
            ->send();

        $this->queue->getConsumer()->consume(80);
        $this->assertEquals(UserLoggedInEmitWorker::$receivedData, null);

        $this->queue->getConsumer()->consume(70);
        $this->assertEquals(UserLoggedInEmitWorker::$receivedData, [
            'id' => $id,
            'data' => $data,
            'process' => Processor::ACK,
            'beforeExecute' => $data,
            'afterExecute' => $data,
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);
    }
}
