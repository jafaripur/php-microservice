# MicroService Server & Client

![test](https://github.com/jafaripur/php-microservice/actions/workflows/run-test.yml/badge.svg)
[![Documentation Status](https://readthedocs.org/projects/php-microservice/badge/?version=latest)](https://php-microservice.readthedocs.io/en/latest/?badge=latest)

Standalone package to implement messaging between microservices nodes in PHP with both server and client.
Server consumers listen to receive message and process, client pushing data to message queue to server consumers grab and process.

## Features

- Use PHP AMQP extension, fallback to Bunny library if `ext-amqp` not installed.
- PSR-3 compatible logging system.
- PSR-11 compatible service container for dependecy injection on processor file.
- Define processor class for responding to client calling methods.
- Define consumer class and attach processors for it for starting consuming.
- Async command sending.
- Each microservice could be a server or client and can talk to each other.
- Client for sending messages to queue.

## Installation

The preferred way to install this package is through composer:

```bash

composer require jafaripur/php-microservice

```

## Documentation

For more details, see full document [http://php-microservice.readthedocs.io/](http://php-microservice.readthedocs.io/).

## Test

```sh

# Run test
docker-compose up --build --exit-code-from micro micro

# Stop and remove created containers
docker-compose down

```