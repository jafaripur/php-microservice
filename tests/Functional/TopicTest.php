<?php

namespace Araz\MicroService\Tests\Functional;

use Araz\MicroService\Queue;
use Araz\MicroService\Tests\Functional\Processor\Topic\UserCreatedTopic;
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
                    \Araz\MicroService\Tests\Functional\Consumer\ConsumerTopic::class,
                ]
            );

            $this->queue->getConsumer()->consume(1);
        }
    }

    public function testQueueSendAndReceiveTopic()
    {
        $data = ['id' => 123];

        $this->queue->getSender()->topic('user_changed', 'user_topic_create', $data);
        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserCreatedTopic::$receivedData, $data);

        $data = ['id' => 1234];

        $this->queue->getSender()->topic('user_changed', 'user_topic_update', $data);
        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserCreatedTopic::$receivedData, $data);
    }

    public function testQueueSendAndReceiveTopicDelay()
    {
        $data = ['id' => 123];

        UserCreatedTopic::$receivedData = null;
        $this->queue->getSender()->topic('user_changed', 'user_topic_create', $data, 100);
        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserCreatedTopic::$receivedData, null);
        $this->queue->getConsumer()->consume(120);
        $this->assertEquals(UserCreatedTopic::$receivedData, $data);


        UserCreatedTopic::$receivedData = null;
        $this->queue->getSender()->topic('user_changed', 'user_topic_update', $data, 100);
        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserCreatedTopic::$receivedData, null);
        $this->queue->getConsumer()->consume(120);
        $this->assertEquals(UserCreatedTopic::$receivedData, $data);
    }
}
