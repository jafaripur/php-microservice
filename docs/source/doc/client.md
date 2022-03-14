# Client

In defining [`queue`](create_queue_instance) We config the current object is able to work as client or just a server or support both server and client. For sending request to another service or send and receive we can use this client library.

## Command

```{code-block} php

<?php

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

```{code-block} php

<?php

$queueName = 'user_service_command';
$jobName = 'get_profile_info';

$asyncTimeout = 20000; // 20000 timeout for the whole command list, if all messages not received in this period exception will be raised.
$commandTimeout = 2000; // 2000 for timeout per message, This timeout is autoremove message
$priority = 0;

$commands = $queue->getSender()->async($asyncTimeout)
    ->command(
        queueName: $queueName,
        jobName: $jobName,
        data: ['id' => 1],
        correlationId: 'test1_id_unique_1',
        timeout: $commandTimeout,
        priority: $priority
    )
    ->command(
        queueName: $queueName,
        jobName: $jobName,
        data: ['id' => 2],
        correlationId: 'test1_id_unique_2',
        timeout: $commandTimeout,
        priority: $priority
    )
    ->command(
        queueName: $queueName,
        jobName: $jobName,
        data: ['id' => 3],
        correlationId: 'test1_id_unique_3',
        timeout: $commandTimeout,
        priority: $priority
    );


// Do more action ...

// In the last use receive to get data back from server
foreach($commands->receive() as $correlationId => $response) {

}

```

In sending command this exception maybe throw: 

- `Araz\MicroService\Exceptions\CommandTimeoutException` Timeout reached.
- `Araz\MicroService\Exceptions\CommandRejectException` Our command message is rejects by the server.
- `Araz\MicroService\Exceptions\SerializerNotFoundException` Response serializes not support in our system.
- `Araz\MicroService\Exceptions\CorrelationInvalidException` Correlation received is invalid.

## Worker

```{code-block} php

<?php

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

## Emit

```{code-block} php

<?php

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

## Topic

```{code-block} php

<?php

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