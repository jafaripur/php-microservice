# MicroService Server & Client

Standalone package to implement messaging between microservices nodes in PHP with both server and client.
Server consumers listen to receive message and process, client pushing data to message queue to server consumers grab and process.

## Features

- Use PHP AMQP extension, fallback to Bunny library if `ext-amqp` not installed.
- PSR-3 compatible logging system.
- PSR-11 compatible service container for dependecy injection on processor file.
- Define processor class for responding to client calling methods.
- Async command sending.
- Each microservice could be a server or client and can talk to each other.
- Client for sending messages to queue.

## Installation

The preferred way to install this package is through composer:

```bash
composer require jafaripur/php-microservice
```

## Configuration

```php

use Araz\MicroService\Queue;

$queue = new Queue(
    'micro-app-1',
    [
        // can be change to another transporter
        // I used RabbitMQ-amqp for message broker.
        'dsn' => 'amqp+rabbitmq+ext://guest:guest@rabbitmq:5672/micro3?heartbeat_on_tick=1',
        //configuration for rabbitmq
        'lazy' => true,
        'persisted' => true,
        'heartbeat' => 10,
        "qos_prefetch_count" => 1,
    ],  
    null, // Loger with Psr\Log\LoggerInterface PSR-3 compatible
    null, // Service container with Psr\Container\ContainerInterface PSR-11 compatible
    true, // Enable client
    true, // Enable consumer
    [
        //Consumers
        Application\Queue\Consumer\ConsumerFirst::class,
        Application\Queue\Consumer\ConsumerSecond::class,
    ]
)
```

## Processors

- `Command`: Send message to queue and wait for a response back from consumers. Like an RPC call, but with messaging queue.
- `Worker`: Send message to queue for doing background process, Related consumer gets message from the queue and process data.
- `Emit`: Send message to all consumers, which consume a specific topic.
- `Topic`: Send message to all consumers, based on topic and routing key.

If the service container configured, the dependency will be injected in `__construct`

```php

public function __construct(Queue $queue, ProcessorConsumer $processorConsumer, LoggerInterface $logger)
{
    parent::__construct($queue, $processorConsumer);
}

```

`$queue` and `$processorConsumer` always passed and should send to the parent constructor. `$logger` is our dependency and loaded from defined service container.

### Command

The command sends a request to queue and wait to get a response, distribute command among multiple consumers, after handling the message, consumer back the result to sender.

```php

declare(strict_types=1);

namespace Application\Queue\Processor\User\Command;

use Araz\MicroService\Processors\Command;

final class UserGetInfoCommand extends Command
{
    public function execute(mixed $body): mixed
    {
        return [
            'id' => 123,
            'name' => 'Test',
        ];
    }

    public function getJobName(): string
    {
        return 'get_profile_info';
    }

    public function getQueueName(): string
    {
        return 'user_service_command';
    }
}

```

#### Usage of command

```php

$queueName = 'user_service_command';
$jobName = 'get_profile_info';
$data = ['id' => 123];
$timeout = 3000; // 3 second timeout if a response not receive in this period, default is 10 second
$priority = 0; // 0-5 priority, Higher is high priority, default is null

$result = $queue->getSender()->command()
    ->setQueueName($queueName)
    ->setJobName($jobName)
    ->setData($data)
    ->setTimeout($timeout)
    ->setPriority($priority)
    ->send();

```

For async command and sending several commmand:

```php

$asyncTimeout = 20000; // 20000 timeout for the whole command list, if all messages not received in this period exception will be raised.
$commandTimeout = 2000; // 2000 for timeout per message, This timeout is autoremove message
$priority = 0;

$commands = $queue->getSender()->async($asyncTimeout)
    ->command('user_service_command', 'get_profile_info', ['id' => 1], 'test1_id_unique_1', $commandTimeout, $priority)
    ->command('user_service_command', 'get_profile_info', ['id' => 2], 'test1_id_unique_2', $commandTimeout, $priority)
    ->command('user_service_command', 'get_profile_info', ['id' => 3], 'test1_id_unique_3', $commandTimeout, $priority);


// Do more action ...

// In the last use receive to get data
foreach($commands->receive() as $correlationId => $data) {

    /**
     * 
     * $data sample
     * ack can be reject, requeue, ack, means command result status
     * 
     * Array
     *   (
     *       [ack] => ack
     *       [result] => Array
     *           (
     *               [id] => 123
     *               [name] => Test
     *           )
     *   )
     * 
     */
}

```

Command exception:
- `Araz\MicroService\Exceptions\CommandTimeoutException` Timeout reached.
- `Araz\MicroService\Exceptions\CommandRejectException` Our command message is rejects by the server.
- `Araz\MicroService\Exceptions\SerializerNotFoundException` Response serializes not support in our system.
- `Araz\MicroService\Exceptions\CorrelationInvalidException` Correlation received is invalid.

### Worker

Distribute time-consuming tasks among multiple workers, consumers can get the message and do the process.

```php

declare(strict_types=1);

namespace Application\Queue\Processor\User\Worker;

use Araz\MicroService\Processors\Worker;

final class UserProfileAnalysisWorker extends Worker
{
    public function execute(mixed $body): void
    {
        // Do you work.
    }

    public function getJobName(): string
    {
        return 'user_profile_analysis';
    }

    public function getQueueName(): string
    {
        return 'user_service_worker';
    }
}

```

#### Usage of worker

```php

$queueName = 'user_service_worker';
$jobName = 'user_profile_analysis';
$data = ['id' => 123];
$priority = 5; // 0-5 priority, Higher is high priority, default is null
$expiration = 0; // expire message in specific millisecond if message not acknowledged
$delay = 30000; // 30s

// This message will be delayed for 30000 millisecond (30s), message with delay and expiration is not possible.

$messageId = $queue->getSender()->worker()
    ->setQueueName($queueName)
    ->setJobName($jobName)
    ->setData($data)
    ->setPriority($priority)
    ->setExpiration($expiration)
    ->setDelay($delay)
    ->send();

```

### Emit

Emit send a request to all consumers, which listen to a specific topic.

```php

declare(strict_types=1);

namespace Application\Queue\Processor\User\Emit;

use Araz\MicroService\Processors\Emit;

final class UserLoggedInEmit extends Emit
{
    public function execute(mixed $body): void
    {
        // Run emit
    }

    public function getTopicName(): string
    {
        return 'user_logged_in';
    }

    public function getQueueName(): string
    {
        return sprintf('%s.user_logged_in_emit', $this->getQueue()->getAppName());
    }
}

```

#### Usage of emit

```php

$topic = 'user_logged_in';
$data = ['id' => 123];
$delay = 5000 // 5 second as millisecond

// This message will emit to consumer with 5 second delay

$messageId = $queue->getSender()->emit($topic, $data, $delay);

$messageId = $queue->getSender()->emit()
    ->setTopicName($topic)
    ->setData($data)
    ->setDelay($delay)
    ->send();

```

All consumer which listen on `user_logged_in` job name receive emit message.

### Topic

Topic send a request to all consumers, which listen to a specific topic and routing key.

```php

declare(strict_types=1);

namespace Application\Queue\Processor\User\Topic;

use Araz\MicroService\Processors\Topic;

final class UserCreatedTopic extends Topic
{
    public function execute(string $routingKey, mixed $body): void
    {
        // Run topic
    }

    public function getTopicName(): string
    {
        return 'user_changed';
    }

    public function getRoutingKeys(): array
    {
        return [
            'user_topic_create',
            'user_topic_update',
        ];
    }

    public function getQueueName(): string
    {
        return sprintf('%s.user_created_topic', $this->getQueue()->getAppName());
    }
}


```

#### Usage of topic

```php

$topic = 'user_changed';
$routingKey = 'user_topic_create';
$data = ['id' => 123];
$delay = 0 // millisecond, 0 disable delay

$messageId = $queue->getSender()->emit()
    ->setTopicName($topic)
    ->setRoutingKey($routingKey)
    ->setData($data)
    ->setDelay($delay)
    ->send();

```

This processor will receive both of `user_topic_create` and `user_topic_update` in topic: `user_changed`.

## Consumer

```php

namespace Application\Queue\Consumer;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerFirst extends ProcessorConsumer
{

    public function getConsumerIdentify(): string {
        return 'first-consumer';
    }

    public function getProcessors(): Generator {

        //Command
        yield \Application\Queue\Processor\User\Command\UserGetInfoCommand::class;

        //Emits
        yield  \Application\Queue\Processor\User\Emit\UserLoggedInEmit::class;

        //Topics
        yield  \Application\Queue\Processor\User\Topic\UserCreatedTopic::class;

        //Workers
        yield  \Application\Queue\Processor\User\Worker\UserProfileAnalysisWorker::class;
    }

}

```

Another consumer:

```php

namespace Application\Queue\Consumer;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerSecond extends ProcessorConsumer
{

    public function getConsumerIdentify(): string {
        return 'second-consumer';
    }

    public function getProcessors(): Generator {
        //Workers
        yield  \Application\Queue\Processor\User\Worker\UserProfileAnalysisWorker::class;
    }

    public function getPrefetchCount(): int
    {
        return 2;
    }

}

```

Each consumer is configurable, check `ProcessorConsumer` class for an existing method to override method, for example:

```php

// Set prefetch count to all processors included in related consumer
public function getPrefetchCount(): int
{
    return 1;
}

```

For start consumer in CLI:

```php

$queue->getConsumer()->consume();

// With timout in 5 second
//$queue->getConsumer()->consume(5000);

```

For listen on specific consumer:

```php

$queue->getConsumer()->consume(0, ['first-consumer', 'second-consumer']);

```

## Application Skeleton Template

This application a template for microservice application and implement four methods of this library.

Spiral framework: [jafaripur/php-microservice-application](https://github.com/jafaripur/php-microservice-application)

Yii3 framework: [jafaripur/php-microservice-application-yii3](https://github.com/jafaripur/php-microservice-application-yii3)

Yii2 framework: [jafaripur/php-microservice-application-yii2](https://github.com/jafaripur/php-microservice-application-yii2)

## Test

```sh

# Run test
docker-compose up --build micro

# Stop and remove created containers
docker-compose down

```