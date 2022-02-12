# Introduction

With this structure we can define our processors class and listen to a specific action.

## Features

- Use PHP AMQP extension, fallback to Bunny library if `ext-amqp` not installed.
- PSR-3 compatible logging system.
- PSR-11 compatible service container for dependecy injection on processor file.
- Define processor class for responding to client calling methods.
- Define consumer class and attach processors for it for starting consuming.
- Async command sending.
- Each microservice could be a server or client and can talk to each other.
- Client for sending messages to queue.

## Install

The preferred way to install this package is through composer:

```{code-block} bash

composer require jafaripur/php-microservice

```

Github: [https://github.com/jafaripur/php-microservice](https://github.com/jafaripur/php-microservice)