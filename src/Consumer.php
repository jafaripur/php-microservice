<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Araz\MicroService\Processors\Command;
use Araz\MicroService\Processors\Worker;
use Araz\MicroService\Processors\Emit;
use Araz\MicroService\Processors\Topic;
use Closure;
use Generator;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\Impl\AmqpMessage;
use Interop\Amqp\Impl\AmqpTopic;
use Psr\Container\ContainerInterface;
use Yiisoft\Injector\Injector;

final class Consumer
{
    /**
     * Map each consumer tag with ProcessorConsumer identify
     *
     * @var array<int, string>
     */
    private array $consumersMapping = [];

    /**
     * Processors list categorized with method
     *
     *
     * [method][key] = Processor
     *
     * key automatic generated with method and processor object hash (Object location)
     *
     * @var array<string, array<int, Processor>>
     */
    private array $processors = [];

    /**
     * Link $processors `key` object location to key generated with `getProcessorKey(...)`
     *
     * @var array<int, int>
     */
    private array $processorsMapping = [];

    /**
     * Loaded Processors consumer
     *
     * consumer_identity => ProcessorConsumer
     *
     * @var array<string, ProcessorConsumer>
     */
    private array $processorConsumersLoaded = [];

    /**
     * Injector for push dependency injection on create Processor and ProcessorConsumer class
     *
     * @var Injector|null $containerInjecter
     */
    private ?Injector $containerInjecter;

    /**
     *
     * @param  Queue                   $queue                    Currently running queue (sender object)
     * @param  ContainerInterface|null $container                Service container
     * @param  string[]                $processorConsumerClasses list of consumer classes
     */
    public function __construct(private Queue $queue, ?ContainerInterface $container = null, private array $processorConsumerClasses = [])
    {
        $this->containerInjecter = $container ? new Injector($container) : null;
    }

    /**
     *
     * @param  integer $timeout as millisecond, 0 mean infinity
     * @param  string[]   $consumers
     * @return void
     */
    public function consume(int $timeout = 0, array $consumers = []): void
    {
        if ($timeout < 0) {
            throw new \LogicException('Timeout can not be less than 0');
        }

        /**
         * If using php-amqp ext, check here:
         * https://github.com/php-amqplib/php-amqplib#unix-signals
         */
        /*pcntl_signal(SIGINT, function () {
            $this->queue->getLogger()->warning('Forced to be terminate, Wait to complete current process...');
        });*/

        $this->initConsumers($consumers);

        $subscriptionConsumer = $this->queue->createSubscriptionConsumer();
        $consumers = $this->getAailableConsumer();

        /**
         * @var AmqpConsumer $consumer
         */
        foreach ($consumers as $consumerIdentify => $consumer) {
            $subscriptionConsumer->subscribe($consumer, Closure::fromCallable([$this, 'receiveCallback']));
            $this->consumersMapping[$this->hashKey($consumer->getConsumerTag())] = $consumerIdentify;
        }

        if (!$consumers->getReturn()) {
            throw new \LogicException('Consumer have not processor to create.');
        }

        $subscriptionConsumer->consume($timeout);

        $subscriptionConsumer->unsubscribeAll();

        $this->consumersMapping = [];
        $this->processors = [];
        $this->processorsMapping = [];
        $this->processorConsumersLoaded = [];
    }

    private function returnReceiveCallbackResult(bool $result, string $method, ?Processor $processor = null): bool
    {
        if ($processor && $processor->resetAfterProcess()) {
            $this->addProcessor($method, $this->createProcessorObject($processor->getProcessorConsumer(), $processor::class));
        }

        gc_collect_cycles();
        return $result;
    }
    /**
     * Trigger when a message is received
     *
     * @param AmqpMessage  $message
     * @param AmqpConsumer $consumer
     *
     * @return bool with returning false, consuming will be stop, true to continue.
     */
    private function receiveCallback(AmqpMessage $message, AmqpConsumer $consumer): bool
    {
        $processorConsumer = $this->findProcessorConsumer($consumer->getConsumerTag());

        /**
         * @var string
         */
        $method = MessageProperty::getMethod($message, '');

        if (!in_array($method, (array)$this->queue::METHODS, true)) {
            $consumer->reject($message, false);
            $this->queue->getLogger()->critical('Unknow method received in consuming', $message->getProperties() + $message->getHeaders());
            return $this->returnReceiveCallbackResult(true, $method);
        }

        if ($this->checkRedelivered($method, $processorConsumer, $message, $consumer)) {
            $processorConsumer->messageRedelivered($message, $consumer);
            return $this->returnReceiveCallbackResult(true, $method);
        }

        $processorConsumer->messageReceived($message, $consumer);

        /**
         * @var string
         */
        $job = MessageProperty::getJob($message, '');

        /**
         * @var string
         */
        $topic = MessageProperty::getTopic($message, '');

        /**
         * @var string
         */
        $queueName = MessageProperty::getQueue($message, '');

        /**
         * @var string
         */
        $serialize = MessageProperty::getSerializer($message, '');

        $serializer = $this->queue->getSerializer($serialize, true);

        if (!$serializer) {
            $consumer->reject($message, true);
            $this->queue->getLogger()->critical('Serialize not found in terminal consuming.', $message->getProperties() + $message->getHeaders());
            return $this->returnReceiveCallbackResult(true, $method);
        }

        if ($method == $this->queue::METHOD_JOB_COMMAND && (!$message->getCorrelationId() || !$message->getReplyTo())) {
            $consumer->reject($message, false);
            $this->queue->getLogger()->critical('Wrong command method coming without correlation_id and reply_to', $message->getProperties() + $message->getHeaders());
            return $this->returnReceiveCallbackResult(true, $method);
        }

        /**
         * @var string $routingKey
         */
        $routingKey = '';
        if ($method == $this->queue::METHOD_JOB_TOPIC) {
            $routingKey = (string)$message->getRoutingKey();
        }

        $processor = $this->getProcessorItem($method, $queueName, $topic, $routingKey, $job);

        if (!$processor) {
            $consumer->reject($message, true);
            $this->queue->getLogger()->error('Processor not found!', [
                $message->getProperties() + $message->getHeaders(),
            ]);
            return $this->returnReceiveCallbackResult(true, $method, $processor);
        }

        /**
         * @var mixed
         */
        $messageData = $serializer->unserialize($message->getBody());

        /**
         * @var Command|Worker|Emit|Topic $processor
         */
        if (!$processor->beforeExecute($messageData)) {
            $consumer->reject($message, false);

            if ($processor->isCommand()) {
                $processor->afterMessageReplytoCommand($message->getMessageId(), $this->replyBackMessage($message, null, Processor::REJECT), $message->getCorrelationId(), Processor::REJECT);
            }

            return $this->returnReceiveCallbackResult(true, $method, $processor);
        }

        $executeResult = $processor->isTopic() ? $processor->execute($routingKey, $messageData) : $processor->execute($messageData);

        $processor->afterExecute($messageData);

        /**
         * @var string $ackResult
         */
        switch ($ackResult = $processor->process($message, $consumer)) {
            case Processor::ACK:
                $consumer->acknowledge($message);
                break;
            case Processor::REJECT:
                $consumer->reject($message, false);
                break;
            case Processor::REQUEUE:
                $consumer->reject($message, true);
                break;
            default:
                throw new \LogicException(sprintf('Acknowledge status is not supported: %s', $ackResult));
        }

        $processor->afterMessageAcknowledge($ackResult);
        $processorConsumer->afterMessageAcknowledge($processor, $ackResult, $message, $consumer);

        if ($processor->isCommand()) {
            $processor->afterMessageReplytoCommand($message->getMessageId(), $this->replyBackMessage($message, $executeResult, $ackResult), $message->getCorrelationId(), $ackResult);
        }

        return $this->returnReceiveCallbackResult(true, $method, $processor);
    }

    /**
     * After receive command, We reply result to producer
     *
     * @param AmqpMessage $message
     * @param mixed       $result  result of command for reply back
     * @param string      $status  status of reply, ack, reject or requeue
     *
     * @return null|string message id
     */
    private function replyBackMessage(AmqpMessage $message, mixed $result, string $status): string|null
    {

        /**
         * @var AmqpMessage
         */
        $replyMessage = $this->queue->createMessage($result, false);
        $replyMessage->setCorrelationId($message->getCorrelationId());
        $replyMessage->setReplyTo($message->getReplyTo());
        MessageProperty::setStatus($replyMessage, $status);

        $this->queue->createProducer()
            ->send($this->queue->createQueue($message->getReplyTo()), $replyMessage);

        return $replyMessage->getMessageId();
    }

    /**
     * Check a message should be redelivery or not
     *
     * @param  string                  $method
     * @param  ProcessorConsumer       $processorConsumer
     * @param  AmqpMessage             $message
     * @param  AmqpConsumer            $consumer
     * @return boolean
     */
    private function checkRedelivered(string $method, ProcessorConsumer $processorConsumer, AmqpMessage $message, AmqpConsumer $consumer): bool
    {
        if (!$message->isRedelivered()) {
            return false;
        }

        $redeliveryCount = (int) MessageProperty::getRedeliver($message);

        if ($redeliveryCount > $processorConsumer->getMaxRedeliveryRetry()) {
            $consumer->reject($message, false);
            $this->queue->getLogger()->critical('Maximum redelivery is reached.', [
                'maximum' => $processorConsumer->getMaxRedeliveryRetry(),
                'msg' => $message->getProperties() + $message->getHeaders(),
            ]);

            $processorConsumer->messageRedeliveredMaximumReached($message, $consumer);

            return true;
        }

        MessageProperty::setRedeliver($message, (string)($redeliveryCount + 1));

        /**
         * @var string $queueName
         */
        if ($method == $this->queue::METHOD_JOB_WORKER || $method == $this->queue::METHOD_JOB_COMMAND) {
            $singleActiveConsumer = $processorConsumer->getSingleActiveConsumer();
            $queueName = (string)MessageProperty::getQueue($message);
        } else {
            $singleActiveConsumer = true;
            $queueName = (string)$message->getRoutingKey();
        }

        $queue = $this->queue->createQueue($queueName);
        $queue->setArgument('x-single-active-consumer', $singleActiveConsumer);

        $this->queue->createProducer()
                ->setDeliveryDelay($processorConsumer->getRedeliveryDelayTime())
                ->send($queue, $message);

        $consumer->reject($message, false);

        return true;
    }

    /**
     * Create ProcessorConsumer object and related processor
     *
     * @param array $consumers list of consumers should be load
     *
     * @return void
     */
    private function initConsumers(array $consumers): void
    {
        $consumerCount = count($consumers);

        /**
         * @var string $class
         * @psalm-var class-string $class
         */
        foreach ($this->processorConsumerClasses as $class) {
            if (!is_subclass_of($class, ProcessorConsumer::class)) {
                throw new \LogicException(sprintf('%s is not implement ProcessorConsumer', $class));
            }

            /**
             * @var ProcessorConsumer
             */
            $processorConsumer = $this->containerInjecter ? $this->containerInjecter->make($class, [
                'queue' => $this->queue,
            ]) : new $class($this->queue);

            if ($consumerCount && !in_array($processorConsumer->getConsumerIdentify(), $consumers, true)) {
                continue;
            }

            if (array_key_exists($processorConsumer->getConsumerIdentify(), $this->processorConsumersLoaded)) {
                throw new \LogicException(sprintf('Duplicate consumer identify in %s', $class));
            }

            $this->processorConsumersLoaded[$processorConsumer->getConsumerIdentify()] = $processorConsumer;

            $this->queue->getLogger()->info(sprintf('Consumer loaded: %s', $class));

            $this->createProcessorsOfConsumer($processorConsumer);
        }
    }

    /**
     * Load and create processor which is defined in ProcessorConsumer class
     *
     * @param  ProcessorConsumer $processorConsume
     * @param  string[]             $methods
     * @return void
     */
    private function createProcessorsOfConsumer(ProcessorConsumer $processorConsume): void
    {
        /**
         * @var string $class
         */
        foreach ($processorConsume->getProcessors() as $class) {
            if (is_subclass_of($class, Command::class)) {
                $method = $this->queue::METHOD_JOB_COMMAND;
            } elseif (is_subclass_of($class, Worker::class)) {
                $method = $this->queue::METHOD_JOB_WORKER;
            } elseif (is_subclass_of($class, Topic::class)) {
                $method = $this->queue::METHOD_JOB_TOPIC;
            } elseif (is_subclass_of($class, Emit::class)) {
                $method = $this->queue::METHOD_JOB_EMIT;
            } else {
                throw new \LogicException(sprintf('Processor not support: %s', $class));
            }

            $processor = $this->createProcessorObject($processorConsume, $class);
            $processor->validateProcessor();

            $this->addProcessor($method, $processor);
            $this->queue->getLogger()->info(sprintf('Processor loaded: %s', $class));
        }
    }

    /**
     * Create processor object
     *
     * @param  ProcessorConsumer $processorConsumer
     * @param  string            $class
     * @psalm-param class-string $class
     * @return Processor
     */
    private function createProcessorObject(ProcessorConsumer $processorConsumer, string $class): Processor
    {
        return $this->containerInjecter instanceof Injector ? $this->containerInjecter->make($class, [
            'queue' => $this->queue,
            'processorConsumer' => $processorConsumer,
        ]) : new $class($this->queue, $processorConsumer);
    }

    /**
     * Get list of available consumer for listen for them
     *
     * @psalm-return Generator<string, AmqpConsumer>
     */
    private function getAailableConsumer(): Generator
    {
        $commands = [];
        $workers = [];
        $topics = [];
        $emits = [];
        $all = [];

        foreach ($this->processorConsumersLoaded as $consumerIdentify => $processorConsumer) {

            /**
             * @var Worker $processor
             */
            foreach ($this->getProcessorItems((string)$this->queue::METHOD_JOB_WORKER) as $processor) {
                $key = $processor->getQueueName().$consumerIdentify;

                if (array_key_exists($key, $workers)) {
                    continue;
                }

                if (array_key_exists($key, $all)) {
                    throw new \LogicException(sprintf('Duplicate queue for creating worker method: %s', $processor->getQueueName()));
                }

                $this->queue->setQos(0, $processorConsumer->getPrefetchCount(), false);

                $queue = $this->queue->createQueue($processor->getQueueName(), $processor->durableQueue(), $processor->getQueueTtl());
                $queue->setArgument('x-single-active-consumer', $processorConsumer->getSingleActiveConsumer());

                $this->queue->declareQueue($queue);

                $workers[$key] = true;
                $all[$key] = true;

                yield $consumerIdentify => $this->queue->createConsumer($queue);

                $this->queue->setQos(0, 1, false);
            }

            /**
             * @var Command $processor
             */
            foreach ($this->getProcessorItems((string)$this->queue::METHOD_JOB_COMMAND) as $processor) {
                $key = $processor->getQueueName().$consumerIdentify;

                if (array_key_exists($key, $commands)) {
                    continue;
                }

                if (array_key_exists($key, $all)) {
                    throw new \LogicException(sprintf('Duplicate queue for creating command method: %s', $processor->getQueueName()));
                }

                $this->queue->setQos(0, $processorConsumer->getPrefetchCount(), false);

                $queue = $this->queue->createQueue($processor->getQueueName(), false, $processor->getQueueTtl());
                $queue->setArgument('x-single-active-consumer', $processorConsumer->getSingleActiveConsumer());
                $this->queue->declareQueue($queue);

                $commands[$key] = true;
                $all[$key] = true;

                yield $consumerIdentify => $this->queue->createConsumer($queue);

                $this->queue->setQos(0, 1, false);
            }

            /**
             * @var Emit $processor
             */
            foreach ($this->getProcessorItems((string)$this->queue::METHOD_JOB_EMIT) as $processor) {
                $key = $processor->getQueueName().$consumerIdentify;

                if (array_key_exists($key, $emits)) {
                    continue;
                }

                if (array_key_exists($key, $all)) {
                    throw new \LogicException(sprintf('Duplicate queue for creating emit method: %s', $processor->getQueueName()));
                }

                $queue = $this->queue->createQueue($processor->getQueueName(), $processor->durableQueue(), $processor->getQueueTtl());
                $queue->setArgument('x-single-active-consumer', true);
                $this->queue->declareQueue($queue);

                $topic = $this->queue->createTopic($processor->getTopicName());
                $topic->setType(AmqpTopic::TYPE_FANOUT);
                $this->queue->declareTopic($topic);
                $this->queue->bind($topic, $queue);
                
                $emits[$key] = true;
                $all[$key] = true;

                yield $consumerIdentify => $this->queue->createConsumer($queue);
            }

            /**
             * @var Topic $processor
             */
            foreach ($this->getProcessorItems((string)$this->queue::METHOD_JOB_TOPIC) as $processor) {
                $key = $processor->getQueueName().$consumerIdentify;

                if (array_key_exists($key, $topics)) {
                    continue;
                }

                if (array_key_exists($key, $all)) {
                    throw new \LogicException(sprintf('Duplicate queue for creating topic method: %s', $processor->getQueueName()));
                }

                $queue = $this->queue->createQueue($processor->getQueueName(), $processor->durableQueue(), $processor->getQueueTtl());
                $queue->setArgument('x-single-active-consumer', true);
                $this->queue->declareQueue($queue);

                $topic = $this->queue->createTopic($processor->getTopicName());
                $topic->setType(AmqpTopic::TYPE_DIRECT);
                $this->queue->declareTopic($topic);

                /**
                 * @var string $routingKey
                 */
                foreach ($processor->getRoutingKeys() as  $routingKey) {
                    $this->queue->bind($topic, $queue, $routingKey);
                }

                $topics[$key] = true;
                $all[$key] = true;

                yield $consumerIdentify => $this->queue->createConsumer($queue);
            }
        }

        return count($all);
    }

    /**
     * Find ProcessorConsumer by consumer tag
     *
     * @param  string            $consumerTag
     * @return ProcessorConsumer
     *
     * @throws \LogicException
     *
     */
    private function findProcessorConsumer(string $consumerTag): ProcessorConsumer
    {
        $processorConsumerIdentify = $this->consumersMapping[$this->hashKey($consumerTag)] ?? null;

        if ($processorConsumerIdentify && ($consumerProcessor = $this->processorConsumersLoaded[$processorConsumerIdentify] ?? null)) {
            return $consumerProcessor;
        }

        throw new \LogicException(sprintf('Processor consumer not found with this tag : %s', $consumerTag));
    }

    /**
     * Generate key for processor based on data
     *
     * @param string $method
     * @param string $queueName
     * @param string $topicName
     * @param string $routingKey
     * @param string $jobName
     *
     * @return int
     */
    private function getProcessorKey(string $method, string $queueName, string $topicName, string $routingKey, string $jobName): int
    {
        return $this->hashKey($method.$queueName.$topicName.$routingKey.$jobName);
    }

    /**
     * Get processor based on parameters received from message broker
     *
     * @param  string    $method
     * @param  string    $queueName
     * @param  string    $topicName
     * @param  string    $routingKey
     * @param  string    $jobName
     * @return Processor|null
     */
    private function getProcessorItem(string $method, string $queueName, string $topicName, string $routingKey, string $jobName): ?Processor
    {
        $key = $this->getProcessorKey($method, $queueName, $topicName, $routingKey, $jobName);

        $objectLocationKey = $this->processorsMapping[$key] ?? null;

        return $objectLocationKey ? ($this->processors[$method][$objectLocationKey] ?? null) : null;
    }

    /**
     * List of processor for specific method
     *
     * @param  string $method
     * @return array<int, Processor>
     */
    private function getProcessorItems(string $method): array
    {
        return $this->processors[$method] ?? [];
    }

    /**
     * Add created processor to local storage for using it when a message received
     *
     * @param  string    $method
     * @param  Processor $processor
     * @return void
     */
    private function addProcessor(string $method, Processor $processor): void
    {
        $objectLocationKey = $this->hashKey($processor::class);

        $this->processors[$method][$objectLocationKey] = $processor;

        $this->generateObjectIdentify($method, $objectLocationKey, $processor);
    }

    /**
     * Generate identify for processor
     *
     * @param  string $method
     * @param  string $objectLocationKey
     * @param  Processor $processor
     * @return void
     */
    private function generateObjectIdentify(string $method, int $objectLocationKey, $processor): void
    {
        $queue = '';
        $job = '';
        $topic = '';
        $routingKeys = [];

        switch ($method) {
            case $this->queue::METHOD_JOB_COMMAND:
            case $this->queue::METHOD_JOB_WORKER:
                /** @var Worker|Command $processor */
                $queue = $processor->getQueueName();
                $job = $processor->getJobName();
                break;
            case $this->queue::METHOD_JOB_EMIT:
                /** @var Emit $processor */
                $topic = $processor->getTopicName();
                break;
            case $this->queue::METHOD_JOB_TOPIC:
                /** @var Topic $processor */
                $topic = $processor->getTopicName();
                $routingKeys = $processor->getRoutingKeys();
                break;
        }

        if (count($routingKeys)) {
            /**
             * @var string $routingKey
             */
            foreach ($routingKeys as $routingKey) {
                $this->processorsMapping[$this->getProcessorKey($method, $queue, $topic, $routingKey, $job)] = $objectLocationKey;
            }
        } else {
            $this->processorsMapping[$this->getProcessorKey($method, $queue, $topic, '', $job)] = $objectLocationKey;
        }
    }

    private function hashKey(string $key): int
    {
        return crc32($key);
    }
}
