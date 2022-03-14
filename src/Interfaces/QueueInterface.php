<?php

declare(strict_types=1);

namespace Araz\MicroService\Interfaces;

use Enqueue\AmqpTools\DelayStrategy;
use Interop\Amqp\Impl\AmqpBind;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\Impl\AmqpMessage;
//use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\AmqpQueue as AmqpQueue;

//use Interop\Amqp\Impl\AmqpTopic;
use Interop\Amqp\AmqpTopic as AmqpTopic;

//use Interop\Amqp\AmqpConsumer;
use Interop\Queue\Consumer as AmqpConsumer;

use Interop\Amqp\AmqpContext;
//use Interop\Amqp\AmqpProducer;
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

    /**
     *
     * @return AmqpContext|Context
     */
    public function getContext(): AmqpContext|Context;

    /**
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface;

    /**
     *
     * Create consumer for a queue
     *
     * @param  AmqpQueue    $queue
     * @return AmqpConsumer
     */
    public function createConsumer(AmqpQueue $queue): AmqpConsumer;

    /**
     *
     * Create subscribe consumer to watch for several queue or topic to receive message
     *
     * @return SubscriptionConsumer
     */
    public function createSubscriptionConsumer(): SubscriptionConsumer;

    /**
     *
     * Create producer to push a message to broker
     *
     * @return AmqpProducer
     */
    public function createProducer(): AmqpProducer;

    /**
    * Create queue
    *
    * @param  string       $name
    * @param  boolean      $durable
    * @param  integer $ttl  time to live as millisecond
    * @return AmqpQueue
    */
    public function createQueue(string $name, bool $durable = true, int $ttl = self::QUEUE_DEFAULT_TTL): AmqpQueue;

    /**
     *
     * Create temporary queue, This type is exlusive and autodelete
     *
     * @return AmqpQueue
     */
    public function createTemporaryQueue(): AmqpQueue;

    /**
     *
     * Declare created queue
     *
     * @param  AmqpQueue $queue
     * @return integer
     */
    public function declareQueue(AmqpQueue $queue): int;

    /**
     * Create topic
     *
     * @param  string    $topic
     * @return AmqpTopic
     */
    public function createTopic(string $topic): AmqpTopic;

    /**
     *
     * Declare created topic
     *
     * @param  AmqpTopic $topic
     * @return void
     */
    public function declareTopic(AmqpTopic $topic): void;

    /**
     *
     * Bind a topic to queue
     *
     * @param  AmqpDestination $target
     * @param  AmqpDestination $source
     * @param  string|null     $routingKey
     * @param  int          $flags
     * @param  array<string, string>  $arguments
     * @return void
     */
    public function bind(AmqpDestination $target, AmqpDestination $source, string $routingKey = null, int $flags = AmqpBind::FLAG_NOPARAM, array $arguments = []): void;

    /**
     * Calls basic.qos AMQP method.
     *
     * @param  integer $prefetchSize
     * @param  integer $prefetchCount
     * @param  boolean $global
     * @return void
     */
    public function setQos(int $prefetchSize, int $prefetchCount, bool $global = false): void;

    /**
     *
     * Set delay strategy
     *
     * @param  DelayStrategy|null $strategy
     * @return void
     */
    public function setDelayStrategy(?DelayStrategy $strategy): void;

    /**
     * Create message with default parameters
     *
     * @param  mixed       $data
     * @param  boolean     $persistent
     * @return AmqpMessage
     */
    public function createMessage(mixed $data, bool $persistent = true): AmqpMessage;

    /**
     * Create unique identify
     *
     * @return string
     */
    public function createUniqueIdentify(): string;

    /**
     *
     *
     * @param  string|null         $serializer class name or serializer name
     * @param  boolean             $findByName find find by name instead class if set to true
     * @return SerializerInterface|null
     *
     * @psalm-ignore-nullable-return
     */
    public function getSerializer(?string $serializer = null, bool $findByName = false): ?SerializerInterface;

    /**
     * Set default serializer
     *
     * @param  string $serializer
     * @return void
     */
    public function setDefaultSerializer(string $serializer): void;

    /**
     * Add new serializer
     *
     * @param  string $serializer class name
     * @return void
     */
    public function addSerializer(string $serializer): void;

    /**
     * Remove a serializer from serializer list
     *
     * @param  string $serializer class name
     * @return void
     */
    public function removeSerializer(string $serializer): void;
}
