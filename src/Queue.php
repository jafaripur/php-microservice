<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Araz\MicroService\Interfaces\QueueInterface;
use Araz\MicroService\Interfaces\SerializerInterface;
use Araz\MicroService\Sender\Client;
use Araz\MicroService\Serializers\IgbinarySerializer;
use Araz\MicroService\Serializers\JsonSerializer;
use Araz\MicroService\Serializers\MessagePackSerializer;
use Araz\MicroService\Serializers\PhpSerializer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Araz\MicroService\Tools\RabbitMqDlxDelayStrategy;
use Enqueue\AmqpBunny\AmqpContext as AmqpBunnyAmqpContext;
use Enqueue\AmqpExt\AmqpContext as AmqpExtAmqpContext;
use Enqueue\AmqpTools\DelayStrategy;
use Interop\Amqp\AmqpContext;
//use Interop\Amqp\AmqpConsumer;
use Interop\Queue\Consumer as AmqpConsumer;

use Interop\Amqp\AmqpDestination;
//use Interop\Amqp\AmqpProducer;
use Interop\Queue\Producer as AmqpProducer;
use Interop\Amqp\Impl\AmqpBind;
//use Interop\Amqp\Impl\AmqpQueue;
use Interop\Amqp\AmqpQueue as AmqpQueue;

use Interop\Amqp\Impl\AmqpMessage;

//use Interop\Amqp\Impl\AmqpTopic;
use Interop\Amqp\AmqpTopic as AmqpTopic;

use Interop\Queue\Context;
use Interop\Queue\SubscriptionConsumer;
use Psr\Container\ContainerInterface;

class Queue implements QueueInterface
{
    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * Server to listen to grab messages
     *
     * @var Consumer|null $consumer
     */
    private $consumer;

    /**
     * Client to send message
     *
     * @var Client|null $client
     */
    private $client;

    private array $serializers = [];

    private bool $lazyQueue = true;

    /**
     *
     *
     * Processor namespace loading recursively from start to end
     *
     *
     * @param  string $appName
     * @param  AmqpConnection $connection
     * @param  LoggerInterface|null $logger
     * @param  ContainerInterface|null $container Service container for resolve processor dependency injection
     * @param  bool $enableClient   Enable client
     * @param  bool $enableConsumer   Enable consumer
     * @param  string[] $processorConsumers array of processor consumer
     * @param  string $serializer default serializer in sending the message with this implement SerializerInterface
     *
     */
    public function __construct(
        private string $appName,
        private AmqpConnection $connection,
        ?LoggerInterface $logger = null,
        ?ContainerInterface $container = null,
        bool $enableClient = true,
        bool $enableConsumer = true,
        array $processorConsumers = [],
        private string $serializer = JsonSerializer::class,
    ) {
        $this->setDefaultSerializer($serializer);
        $this->initSerializer();

        $this->logger = $logger ?? new NullLogger();

        if ($enableClient) {
            $this->setDelayStrategy(new RabbitMqDlxDelayStrategy($this));
            $this->client = new Client($this);
        }

        if ($enableConsumer) {
            $this->consumer = new Consumer($this, $container, $processorConsumers);
        }
    }


    public function getAppName(): string
    {
        return $this->appName;
    }

    /**
     *
     * @return AmqpContext|Context
     */
    public function getContext(): AmqpContext|Context
    {
        return $this->getConnection()->getContext();
    }

    /**
     *
     * @return AmqpConnection
     */
    public function getConnection(): AmqpConnection
    {
        return $this->connection;
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @inheritDoc
     */
    public function getConsumer(): Consumer
    {
        if (!($this->consumer instanceof Consumer)) {
            throw new \LogicException('This queue not support consumer.');
        }

        if (PHP_SAPI != 'cli') {
            throw new \LogicException('Consume just can be run from terminal (console) (php-cli).');
        }

        return $this->consumer;
    }

    /**
     * @inheritDoc
     */
    public function getClient(): Client
    {
        if (!($this->client instanceof Client)) {
            throw new \LogicException('This queue not support client as client.');
        }

        return $this->client;
    }

    /**
     * @inheritDoc
     */
    public function createConsumer(AmqpQueue $queue): AmqpConsumer
    {

        /**
         * @var AmqpExtAmqpContext|AmqpBunnyAmqpContext $context
         */
        $context = $this->getContext();

        return $context->createConsumer($queue);
    }

    /**
     * @inheritDoc
     */
    public function createSubscriptionConsumer(): SubscriptionConsumer
    {
        return $this->getContext()->createSubscriptionConsumer();
    }

    /**
     * @inheritDoc
     */
    public function createProducer(): AmqpProducer
    {
        return $this->getContext()->createProducer();
    }

    /**
     * @inheritDoc
     */
    public function setQos(int $prefetchSize, int $prefetchCount, bool $global = false): void
    {

        /**
         * @var AmqpExtAmqpContext|AmqpBunnyAmqpContext $context
         */
        $context = $this->getContext();

        $context->setQos($prefetchSize, $prefetchCount, $global);
    }

    /**
     * @inheritDoc
     */
    public function createQueue(string $name, bool $durable = true, int $ttl = self::QUEUE_DEFAULT_TTL): AmqpQueue
    {
        if ($ttl < 0) {
            throw new \LogicException('Timeout can not be less than 0');
        }

        /**
         * @var \Interop\Amqp\AmqpQueue $queue
         */
        $queue = $this->getContext()->createQueue($name);

        if ($durable) {
            $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        }

        if ($this->lazyQueue) {
            $queue->setArgument('x-queue-mode', 'lazy');
        }

        $queue->setArgument('x-max-priority', self::MAX_PRIORITY);
        $queue->setArgument('x-expires', $ttl);
        $queue->setArgument('x-app', $this->getAppName());

        return $queue;
    }

    /**
     * @inheritDoc
     */
    public function createTemporaryQueue(): AmqpQueue
    {

        /**
         * @var AmqpExtAmqpContext|AmqpBunnyAmqpContext $context
         */
        $context = $this->getContext();

        return $context->createTemporaryQueue();
    }

    /**
     * @inheritDoc
     */
    public function declareQueue(AmqpQueue $queue): int
    {

        /**
         * @var AmqpExtAmqpContext|AmqpBunnyAmqpContext $context
         */
        $context = $this->getContext();

        return $context->declareQueue($queue);
    }

    /**
     * @inheritDoc
     */
    public function createTopic(string $topic): AmqpTopic
    {

        /**
         * @var AmqpExtAmqpContext|AmqpBunnyAmqpContext $context
         */
        $context = $this->getContext();

        return $context->createTopic($topic);
    }

    /**
     * @inheritDoc
     */
    public function declareTopic(AmqpTopic $topic): void
    {
        /**
         * @var AmqpExtAmqpContext|AmqpBunnyAmqpContext $context
         */
        $context = $this->getContext();

        $context->declareTopic($topic);
    }

    /**
     * @inheritDoc
     */
    public function bind(AmqpDestination $target, AmqpDestination $source, string $routingKey = null, int $flags = AmqpBind::FLAG_NOPARAM, array $arguments = []): void
    {

        /**
         * @var AmqpExtAmqpContext|AmqpBunnyAmqpContext $context
         */
        $context = $this->getContext();

        $context->bind(new AmqpBind($target, $source, $routingKey, $flags, $arguments));
    }

    /**
     * @inheritDoc
     */
    public function setDelayStrategy(?DelayStrategy $strategy): void
    {

        /**
         * @var AmqpExtAmqpContext|AmqpBunnyAmqpContext $context
         */
        $context = $this->getContext();

        $context->setDelayStrategy($strategy);
    }

    public function lazyQueue(bool $lazy): void
    {
        $this->lazyQueue = $lazy;
    }

    /**
     * @inheritDoc
     */
    public function createMessage(mixed $data, bool $persistent = true): AmqpMessage
    {

        /**
         * @var AmqpMessage $message
         */
        $message = $this->getContext()->createMessage($this->getSerializer()->serialize($data));

        $message->setTimestamp(time());

        $message->setMessageId($this->createUniqueIdentify());
        $message->setDeliveryMode($persistent ? AmqpMessage::DELIVERY_MODE_PERSISTENT : AmqpMessage::DELIVERY_MODE_NON_PERSISTENT);
        MessageProperty::setSerializer($message, $this->getSerializer()->getName());
        $message->setContentType($this->getSerializer()->getContentType());

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function createUniqueIdentify(): string
    {
        return uniqid('', true);
    }

    /**
     * @inheritDoc
     * @psalm-ignore-nullable-return
     */
    public function getSerializer(?string $serializer = null, bool $findByName = false): ?SerializerInterface
    {
        if ($findByName && !$serializer) {
            return null;
        }

        if (!$findByName) {
            if (!$serializer) {
                $serializer = $this->serializer;
            }

            if (!isset($this->serializers[$serializer])) {
                //throw new SerializerNotFoundException($serializer);
                return null;
            }

            if (is_bool($this->serializers[$serializer])) {
                /**
                 * @var string $serializer
                 * @psalm-param class-string $serializer
                 */
                $this->serializers[$serializer] = new $serializer();
            }

            return $this->serializers[$serializer] ?? null;
        }

        /**
         * @var string $class
         * @psalm-param class-string $class
         */
        foreach ($this->serializers as $class => $obj) {
            if (is_bool($obj)) {
                $this->serializers[$class] = new $class();
            }

            if ($this->serializers[$class]->getName() == $serializer) {
                return $this->serializers[$class];
            }
        }

        //throw new SerializerNotFoundException($serializer);
        return null;
    }

    /**
     * @inheritDoc
     */
    public function addSerializer(string $serializer): void
    {
        if (!is_subclass_of($serializer, SerializerInterface::class)) {
            throw new \LogicException('The $serializer must be implement of SerializerInterface');
        }

        if (!array_key_exists($serializer, $this->serializers)) {
            $this->serializers[$serializer] = true;
        }
    }

    /**
     * @inheritDoc
     */
    public function removeSerializer(string $serializer): void
    {
        unset($this->serializers[$serializer]);
    }

    /**
     * @inheritDoc
     */
    public function setDefaultSerializer(string $serializer): void
    {
        if (!is_subclass_of($serializer, SerializerInterface::class)) {
            throw new \LogicException('The $serializer must be implemented SerializerInterface');
        }

        $this->serializer = $serializer;

        if (!isset($this->serializers[$serializer])) {
            $this->serializers[$serializer] = true;
        }
    }

    /**
     * Initialize serializer
     *
     * @param  string $serializer
     * @return void
     */
    private function initSerializer(): void
    {
        if (!isset($this->serializers[JsonSerializer::class])) {
            $this->serializers[JsonSerializer::class] = true;
        }

        if (!isset($this->serializers[PhpSerializer::class])) {
            $this->serializers[PhpSerializer::class] = true;
        }

        if (extension_loaded('igbinary')) {
            $this->serializers[IgbinarySerializer::class] = true;
        }

        if (extension_loaded('msgpack')) {
            $this->serializers[MessagePackSerializer::class] = true;
        }
    }
}
