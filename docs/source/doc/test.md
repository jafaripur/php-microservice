# Test

Rabbitmq connection string configuration for test available in `phpunit.xml`. If you installed rabbitmq and ready to accept request we can use this:

```{code-block} bash

php ./vendor/bin/phpunit

```

Also we can run test with docker-compose with creating application and RabbitMQ container.

Run test:

```{code-block} bash

docker-compose up --build --exit-code-from micro micro

```

For stop and remove created containers:

```{code-block} bash

docker-compose down

```


