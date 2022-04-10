# Queue

The first part to use as a server or as a client is the definition of `Queue`.

## Create Queue Instance

```{code-block} php
---
name: create_queue_instance
caption: Create instance of `Queue`
---

<?php

$queue = new \Araz\MicroService\Queue(
    appName: 'micro-app-1',
    connection: new \Araz\MicroService\AmqpConnection(
        transport: [
            'dsn' => 'amqp+rabbitmq+ext://guest:guest@rabbitmq:5672/micro3?heartbeat_on_tick=1',
            'lazy' => true,
            'persisted' => true,
            'heartbeat' => 10,
            "qos_prefetch_count" => 1,
        ],
        amqpLibrary: AmqpConnectionFactory::class // ext-amqp is default used library and can change to AmqpBunnyConnectionFactory::class if ext-amqp not installed
    ),
    logger: null,
    container: null,
    enableClient: true,
    enableConsumer: true,
    processorConsumers: [
        Application\Queue\Consumer\ConsumerFirst::class,
        Application\Queue\Consumer\ConsumerSecond::class,
    ],
    serializer: \Araz\MicroService\Serializers\JsonSerializer::class
)

```

### Parameters

- `appName`: Application name, for example, current node name. This will add to message metadata and can use for another purpose in internal process.
- `connection`: AmqpConnection object to configure RabbitMQ server, transport parameter Array of configuration for rabbitmq. `dsn` for connection string for connecting to Rabbitmq, Other values is the configuration of rabbitmq.
- `logger`: Log system with [PSR-3](https://www.php-fig.org/psr/psr-3/) compatible. This is optional and can be null or an instance of `Psr\Log\LoggerInterface`.
- `container`: [PSR-11](https://www.php-fig.org/psr/psr-11/) compatible service container for dependency injection on [`processor`](processor.md) and p`processor-consumer`](processor-consumer.md) file. This is optional and can be null or an instance of `Psr\Container\ContainerInterface`.
- `enableClient`: Enable current queue object as a client.
- `enableConsumer`: Enable current queue object as a server and able to listen on queues.
- `processorConsumers`: Array of [`Consumer`](processor-consumer.md) class name, Each consumer class can contain several [`Processor`](processor.md) class and configuration for queue.
- `serializer`: Default serializer in sending the message with this implement `Araz\MicroService\Interfaces\SerializerInterface`. Default serializer is `Araz\MicroService\Serializers\JsonSerializer`.

```{note}
Default connection used [ext-amqp](https://github.com/php-amqp/php-amqp), This manually change to use [Bunny](https://github.com/jakubkulhan/bunny).
```

### Lazy Queue

For declare queue as [lazy](https://www.rabbitmq.com/lazy-queues.html) we can use this method before consuming.

```{code-block} php

<?php

$queue->lazyQueue(true);

```

### Serializer

Available serializer for decode in receiving data:

- `Araz\MicroService\Serializers\JsonSerializer`
- `Araz\MicroService\Serializers\IgbinarySerializer`
- `Araz\MicroService\Serializers\MessagePackSerializer`
- `Araz\MicroService\Serializers\PhpSerializer`

For remove or add new serializer, class should implement `Araz\MicroService\Interfaces\SerializerInterface` interface.

#### Add

Add new serializer with class name.

```{code-block} php

<?php

$queue->addSerializer(\Application\MyCustomSerializer::class);

```

#### Remove

Remove serializer with class name.

```{code-block} php

<?php

$queue->removeSerializer(\Application\MyCustomSerializer::class);

```

#### Default Serializer

Set default serializer with class name.

```{code-block} php

<?php

$queue->setDefaultSerializer(\Application\MyCustomSerializer::class);

```

#### Get Serializer

Set default serializer with class name.

```{code-block} php

<?php

if ($serializerObject = $queue->getSerializer(\Application\MyCustomSerializer::class)) {
    $encoded = $serializerObject->serialize([1, 2, 3]);
    $decoded = $serializerObject->unserialize($encoded);
}

```

Also can load a serializer object with the name is defined in each serializer class:

```{code-block} php

<?php

if ($serializerObject = $queue->getSerializer('json', true)) {
    $encoded = $serializerObject->serialize([1, 2, 3]);
    $decoded = $serializerObject->unserialize($encoded);
}

```

If serialize not found based on class or name, `null` value returns.