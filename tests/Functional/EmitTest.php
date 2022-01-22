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

    public function testQueueSendAndReceiveEmit()
    {
        $data = ['id' => 123];

        $this->queue->getSender()->emit('user_logged_in', $data);
        $this->queue->getConsumer()->consume(1);

        $this->assertEquals(UserLoggedInEmit::$receivedData, $data);
    }

    public function testQueueSendAndReceiveTopicDelay()
    {
        $data = ['id' => 123];

        UserLoggedInEmit::$receivedData = null;
        $this->queue->getSender()->emit('user_logged_in', $data, 100);
        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserLoggedInEmit::$receivedData, null);
        $this->queue->getConsumer()->consume(120);
        $this->assertEquals(UserLoggedInEmit::$receivedData, $data);
    }
}
