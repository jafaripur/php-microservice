# Processor Consumer

For connecting to `RabbitMQ` and start consuming message, we should define a consumer class and attach processor in that. Each consumer class have own configuration and methods for override and mandatory methods.

## Mandatory Method

- `getProcessors()`: A list of class name of processors, This method return [Generator](https://www.php.net/manual/en/language.generators.overview.php) type.
- `getConsumerIdentify()`: Custom unique identify of the consumer.

## Optional Method

- `getQueue()`: Access to current [`queue`](create_queue_instance) object which is execute current consumer.
- `messageReceived(AmqpMessage $message, AmqpConsumer $consumer)`: The first method which is executed when a new message received and need to process.
- `messageRedelivered(AmqpMessage $message, AmqpConsumer $consumer)`: Run when a received message redelivered. Some time error occurred in code before acknowledging message, This message going to redeliver to another processor to do action.
- `messageRedeliveredMaximumReached(AmqpMessage $message, AmqpConsumer $consumer)`: For preventing from loop We define a maximum number for redeliver a message. When limit reached  maximum, this method will be run.
- `getMaxRedeliveryRetry`: Maximum number of tries for redeliver message.
- `getRedeliveryDelayTime`: In redeliver action, old message should be requeue again to queue for processing. If we need to push again message to queue in redeliver mode with delay, We can return the number of millisecond to make delay on redeliver message to queue.
- `afterMessageAcknowledge(Processor $processor, string $status, AmqpMessage $message, AmqpConsumer $consumer)`: Run after message acknowledged.
- `processorFinished(?string $status, Processor $processor)`: Will run before complete the processor. status can be : `ack`, `reject`, `requeue` and `null`. In redelivered status set to `null`. `$processor` is the handled processor. 
- `getSingleActiveConsumer()` Enable [Single Active Consumer](https://www.rabbitmq.com/consumers.html#single-active-consumer) for current consumer and processors attached to it.
- `getPrefetchCount()`: Set number of [prefetch-count](https://www.rabbitmq.com/consumer-prefetch.html) for this consumer and processors attached to it.


## Consumer Definition

```{code-block} php
---
name: consumer_definition
caption: Consumer definition
---
<?php

declare(strict_types=1);

namespace Application\Queue\Processor\User\Command;

namespace Application\Queue\Consumer;

use Araz\MicroService\ProcessorConsumer;
use Generator;

final class ConsumerFirst extends ProcessorConsumer
{

    public function getConsumerIdentify(): string {
        return 'first-consumer';
    }

    public function getProcessors(): Generator {
        yield \Application\Queue\Processor\User\Command\UserGetInfoCommand::class;
        yield \Application\Queue\Processor\User\Emit\UserLoggedInEmit::class;
        yield \Application\Queue\Processor\User\Topic\UserCreatedTopic::class;
        yield \Application\Queue\Processor\User\Worker\UserProfileAnalysisWorker::class;
    }

    public function getPrefetchCount(): int
    {
        return 1;
    }
}

```

We can define several consumer class in the application and start all  consumers at once or start separately per consumer.

## Start Consumer

This start to listen all consumers, which is defined in defining [`queue`](create_queue_instance).

```{code-block} php

<?php

$queue->getConsumer()->consume();

```

For listen on specific consumer:

```{code-block} php

<?php

$timeout = 0;
$consumersIdentifies = ['first-consumer', 'second-consumer'];
$queue->getConsumer()->consume($timeout, $consumersIdentifies);

```

We can set timeout as millisecond to stop after that time. `0` mean for infinity.