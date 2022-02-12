# Processor

After configuring and create an instance of [`Queue`](create_queue_instance), we can now define the processor we need in our program.

## Methods

We have four ways to implement processors.

- [Command](https://www.enterpriseintegrationpatterns.com/patterns/messaging/RequestReply.html): If our processor definition is command type, it means that every client should expect an answer when calling this command. Like an RPC call.
- [Worker](https://www.enterpriseintegrationpatterns.com/patterns/messaging/CompetingConsumers.html): Send message to queue for doing background process.
- **Emit**: Send message to many consumers at once.
- **Topic**: Send message to many consumers at once selectively.

The definition of processors means that we are the recipient and can execute our defined processor.

### Processor Methods

Mandatory method of each processor:
- `getQueueName()`: This should be implemented in processor class and is mandatory. Queue name which processor should listen to receive message.

Each processor can override this method and is optional:

- `getQueue()`: Access to current [`queue`](create_queue_instance) object which is execute current processor.
- `getProcessorConsumer()`: Access to current [`processor-consumer`](processor-consumer.md) object which is execute current processor.
- `beforeExecute(mixed $data)`: Run before the `execute()` method.
- `afterExecute(mixed $data)`: Run after the `execute()` method.
- `process(AmqpMessage $message, AmqpConsumer $consumer)`: Run after the `afterExecute()` method to decision current message received should be accept or reject or requeue. return value can be : `ack`, `reject`, `requeue`.
- `afterMessageAcknowledge(string $status)`: Run after message acknowledge. status can be : `ack`, `reject`, `requeue`.
- `resetAfterProcess()`: Return boolean value. If returned value is true, each time message received and execute the processor, processor object should recreate.
- `getQueueTtl()`: Return time as millisecond for time to live of created queue for current processor.

### Command

The `UserGetInfoCommand` sends a request to queue and wait to get a response, distribute command among multiple consumers, after handling the message, consumer back the result to sender.

```{code-block} php
---
name: command_definition
caption: Command definition
---
<?php

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

By defining `UserGetInfoCommand` processor, we will receive any request from the client that is in the queue (`user_service_command`) and job (`get_profile_info`) and we will send a response back to the client.

We can define several processor for same queue and different job.

#### Command Methods

- `execute(mixed $body)`: This should be implemented in command class and is mandatory. Run when new command received, `$body` variable is a data received from client. Any value return from this method reply back to the client as a response.
- `getJobName()`: This should be implemented in command class and is mandatory. Job name of the command.
- `afterMessageReplytoCommand(string $messageId, string $replyId, string $correlationId, string $status)`: This method run after we reply back to command.

### Worker

Distribute time-consuming tasks among multiple workers, consumers can get the message and pass it to the related processor to run.

```{code-block} php
---
name: worker_definition
caption: Worker definition
---
<?php

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

`Worker` is same as `Command` but command need reply-back to client but worker receive message and not need to reply-back to user.

#### Worker Methods

- `execute(mixed $body)`: This should be implemented in worker class and is mandatory. Run when new worker received, `$body` variable is a data received from client.
- `getJobName()`: This should be implemented in worker class and is mandatory. Job name of the worker.
- `durableQueue()`: Return boolean value. If returned value is true, defined queue will be durable.

### Emit

Emit send a request to all consumers at once, which listen to a specific topic.

```{code-block} php
---
name: emit_definition
caption: Emit definition
---
<?php

declare(strict_types=1);

namespace Application\Queue\Processor\User\Emit;

use Araz\MicroService\Processors\Emit;

final class UserLoggedInEmit extends Emit
{
    public function execute(mixed $body): void
    {
        // Do you work.
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

#### Emit Methods

- `execute(mixed $body)`: This should be implemented in emit class and is mandatory. Run when new emit received, `$body` variable is a data received from client.
- `getTopicName()`: This should be implemented in emit class and is mandatory. Topic name should emit listen to it.
- `durableQueue()`: Return boolean value. If returned value is true, defined queue will be durable.

### Topic

Topic send a request to all consumers at once, which listen to a specific topic and routing key.

```{code-block} php
---
name: topic_definition
caption: Topic definition
---
<?php

declare(strict_types=1);

namespace Application\Queue\Processor\User\Topic;

use Araz\MicroService\Processors\Topic;

final class UserCreatedTopic extends Topic
{
    public function execute(string $routingKey, mixed $body): void
    {
        // Do you work.
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

#### Topic Methods

- `execute(string $routingKey, mixed $body)`: This should be implemented in topic class and is mandatory. Run when new topic received, `$body` variable is a data received from client and `$routingKey` to indicate which routing key come to this processor.
- `getTopicName()`: This should be implemented in topic class and is mandatory. Topic name should topic listen to it.
- `getRoutingKeys()`: This should be implemented in topic class and is mandatory. Routing key name should topic listen to it. One topic class can listen to several routing key in same topic.
- `durableQueue()`: Return boolean value. If returned value is true, defined queue will be durable.

## Policies

`Emit` and `Topic` method use [Single Active Consumer](https://www.rabbitmq.com/consumers.html#single-active-consumer) by default.
This is because we may run multiple processes of the program in several nodes and we do not need to receive all of them and only once is enough to receive information for each processor.

If we have a several processor with the same queue, We can not set durable one and non-durable for another. After creating queue in rabbitmq we can not change property like an durable or single active consumer. For update queue arguments, queue should be delete.