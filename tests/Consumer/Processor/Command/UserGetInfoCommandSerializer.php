<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Command;

use Araz\MicroService\Processors\Command;
use Araz\MicroService\Serializers\JsonSerializer;
use Araz\MicroService\Serializers\PhpSerializer;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;

final class UserGetInfoCommandSerializer extends Command
{

    public function execute(mixed $body): mixed
    {
        $this->getQueue()->setDefaultSerializer(PhpSerializer::class);
        return $body;
    }

    public function getQueueName(): string
    {
        return 'service_command';
    }

    public function getJobName(): string
    {
        return 'profile_info_serializer';
    }

    public function durableQueue(): bool
    {
        return false;
    }
    
    public function processorFinished(?string $result): void
    {
        $this->getQueue()->setDefaultSerializer(JsonSerializer::class);   
    }
}
