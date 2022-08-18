<?php

declare(strict_types=1);

namespace Araz\MicroService\Interfaces;

use Enqueue\AmqpTools\DelayStrategy;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\Impl\AmqpMessage;
// use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\AmqpQueue as AmqpQueue;
// use Interop\Amqp\Impl\AmqpTopic;
use Interop\Amqp\AmqpTopic as AmqpTopic;
// use Interop\Amqp\AmqpConsumer;
use Interop\Queue\Consumer as AmqpConsumer;
use Interop\Amqp\AmqpContext;
// use Interop\Amqp\AmqpProducer;
use Interop\Queue\Producer as AmqpProducer;
use Interop\Queue\Context;
use Interop\Queue\SubscriptionConsumer;
use Psr\Log\LoggerInterface;

interface QueueInterface
{
    public const MAX_PRIORITY = 5;

    public const QUEUE_DEFAULT_TTL = 1728000000; // 20 days

    public const METHOD_JOB_EMIT = 'emit';

    public const METHOD_JOB_TOPIC = 'topic';

    public const METHOD_JOB_WORKER = 'worker';

    public const METHOD_JOB_COMMAND = 'command';

    public const METHODS = [
        self::METHOD_JOB_COMMAND,
        self::METHOD_JOB_EMIT,
        self::METHOD_JOB_WORKER,
        self::METHOD_JOB_TOPIC,
    ];

    public function getContext(): AmqpContext|Context;

    public function getLogger(): LoggerInterface;

    /**
     * Create consumer for a queue.
     */
    public function createConsumer(AmqpQueue $queue): AmqpConsumer;

    /**
     * Create subscribe consumer to watch for several queue or topic to receive message.
     */
    public function createSubscriptionConsumer(): SubscriptionConsumer;

    /**
     * Create producer to push a message to broker.
     */
    public function createProducer(): AmqpProducer;

    /**
     * Create queue.
     *
     * @param int $ttl time to live as millisecond
     */
    public function createQueue(string $name, bool $durable = true, int $ttl = self::QUEUE_DEFAULT_TTL): AmqpQueue;

    /**
     * Create temporary queue, This type is exlusive and autodelete.
     */
    public function createTemporaryQueue(): AmqpQueue;

    /**
     * Declare created queue.
     */
    public function declareQueue(AmqpQueue $queue): int;

    /**
     * Create topic.
     */
    public function createTopic(string $topic): AmqpTopic;

    /**
     * Declare created topic.
     */
    public function declareTopic(AmqpTopic $topic): void;

    /**
     * Bind a topic to queue.
     *
     * @param array<string, string> $arguments
     */
    public function bind(AmqpDestination $target, AmqpDestination $source, string $routingKey = null, int $flags = AmqpBind::FLAG_NOPARAM, array $arguments = []): void;

    /**
     * Calls basic.qos AMQP method.
     */
    public function setQos(int $prefetchSize, int $prefetchCount, bool $global = false): void;

    /**
     * Set delay strategy.
     */
    public function setDelayStrategy(?DelayStrategy $strategy): void;

    /**
     * Create message with default parameters.
     */
    public function createMessage(mixed $data, bool $persistent = true): AmqpMessage;

    /**
     * Create unique identify.
     */
    public function createUniqueIdentify(): string;

    /**
     * @param null|string $serializer class name or serializer name
     * @param bool        $findByName find find by name instead class if set to true
     *
     * @psalm-ignore-nullable-return
     */
    public function getSerializer(?string $serializer = null, bool $findByName = false): ?SerializerInterface;

    /**
     * Set default serializer.
     */
    public function setDefaultSerializer(string $serializer): void;

    /**
     * Add new serializer.
     *
     * @param string $serializer class name
     */
    public function addSerializer(string $serializer): void;

    /**
     * Remove a serializer from serializer list.
     *
     * @param string $serializer class name
     */
    public function removeSerializer(string $serializer): void;
}
