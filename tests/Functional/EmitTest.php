<?php

namespace Araz\MicroService\Tests\Functional;

use Araz\MicroService\Queue;
use Araz\MicroService\Tests\Functional\Processor\Emit\UserLoggedInEmit;
use PHPUnit\Framework\TestCase;

class EmitTest extends TestCase
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
                    \Araz\MicroService\Tests\Functional\Consumer\ConsumerEmit::class,
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
            $this->assertStringContainsString('Topic name', $th->getMessage());
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

        $this->queue->getClient()->emit()
            ->setTopicName('user_logged_in')
            ->setData($data)
            ->send();

        $this->queue->getConsumer()->consume(1);

        $this->assertEquals(UserLoggedInEmit::$receivedData, $data);
    }

    public function testQueueSendAndReceiveTopicDelay()
    {
        $data = ['id' => 123];

        UserLoggedInEmit::$receivedData = null;

        $this->queue->getClient()->emit()
            ->setTopicName('user_logged_in')
            ->setData($data)
            ->setDelay(100)
            ->send();

        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserLoggedInEmit::$receivedData, null);
        $this->queue->getConsumer()->consume(120);
        $this->assertEquals(UserLoggedInEmit::$receivedData, $data);
    }
}
