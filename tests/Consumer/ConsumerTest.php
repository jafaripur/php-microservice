<?php

namespace Araz\MicroService\Tests\Consumer;

use Araz\MicroService\Queue;
use Araz\MicroService\Serializers\MessagePackSerializer;
use PHPUnit\Framework\TestCase;

class ConsumerTest extends TestCase
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
                    \Araz\MicroService\Tests\Consumer\Consumer\ConsumerCommand::class,
                    \Araz\MicroService\Tests\Consumer\Consumer\ConsumerCommandEventConsumer::class,
                    \Araz\MicroService\Tests\Consumer\Consumer\ConsumerCommandEventConsumerRedelivery::class,
                    \Araz\MicroService\Tests\Consumer\Consumer\ConsumerEmit::class,
                    \Araz\MicroService\Tests\Consumer\Consumer\ConsumerTopic::class,
                    \Araz\MicroService\Tests\Consumer\Consumer\ConsumerWorker::class,
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
    public function testConsumer(Queue $queue)
    {
        $queue->removeSerializer(MessagePackSerializer::class);
        
        $queue->getConsumer()->consume();
        $this->assertEquals(true, true);
    }

}
