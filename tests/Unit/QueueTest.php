<?php

namespace Araz\MicroService\Tests\Unit;

use Araz\MicroService\AsyncSender;
use Araz\MicroService\Consumer;
use Araz\MicroService\Exceptions\SerializerNotFoundException;
use Araz\MicroService\Interfaces\SerializerInterface;
use Araz\MicroService\Queue;
use Araz\MicroService\Sender;
use Araz\MicroService\Serializers\JsonSerializer;
use Araz\MicroService\Serializers\PhpSerializer;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpSubscriptionConsumer;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\Impl\AmqpTopic;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    public function queueInitDataProvider()
    {
        yield 'queue' => [
            new Queue(
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
                    //Consumers
                ]
            ),
            //Extra param
        ];
    }

    /**
     * @dataProvider queueInitDataProvider
     *
     * @param Queue $queue
     */
    public function testQueueInitialize(Queue $queue)
    {
        $this->assertEquals('test-app', $queue->getAppName());
        $this->assertNotEquals('test-app-dup', $queue->getAppName());

        $this->assertInstanceOf(AmqpContext::class, $queue->getContext());
        $this->assertInstanceOf(AmqpProducer::class, $queue->createProducer());

        $this->assertInstanceOf(AmqpQueue::class, $queue->createTemporaryQueue());
        $queueObject = $queue->createQueue('test');
        $this->assertInstanceOf(AmqpQueue::class, $queueObject);
        $this->assertInstanceOf(AmqpConsumer::class, $queue->createConsumer($queueObject));
        $this->assertInstanceOf(AmqpSubscriptionConsumer::class, $queue->createSubscriptionConsumer());
        $this->assertInstanceOf(AmqpTopic::class, $queue->createTopic('test'));

        $this->assertInstanceOf(AmqpMessage::class, $queue->createMessage('just test'));

        $this->assertInstanceOf(Consumer::class, $queue->getConsumer());
        $this->assertInstanceOf(Sender::class, $queue->getSender());
        $this->assertInstanceOf(AsyncSender::class, $queue->getSender()->async());

        try {
            $queue->getConsumer()->consume(500);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
        }
    }

    /**
     * @dataProvider queueInitDataProvider
     *
     * @param Queue $queue
     */
    public function testQueueSerializer(Queue $queue)
    {
        $this->assertInstanceOf(SerializerInterface::class, $queue->getSerializer());

        try {
            $queue->addSerializer(Consumer::class);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
        }

        $queue->addSerializer(PhpSerializer::class);

        $this->assertInstanceOf(PhpSerializer::class, $queue->getSerializer(PhpSerializer::class));
        $this->assertNotInstanceOf(JsonSerializer::class, $queue->getSerializer(PhpSerializer::class));

        $this->assertEquals($queue->getSerializer(null, true), null);

        $this->assertInstanceOf(JsonSerializer::class, $queue->getSerializer('json', true));

        $this->assertInstanceOf(JsonSerializer::class, $queue->getSerializer());
        $this->assertNotInstanceOf(PhpSerializer::class, $queue->getSerializer());

        $queue->removeSerializer(PhpSerializer::class);

        try {
            $queue->getSerializer(PhpSerializer::class);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(SerializerNotFoundException::class, $th);
        }

        try {
            $queue->getSerializer(Sender::class);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
        }
    }
}
