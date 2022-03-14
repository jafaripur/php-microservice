<?php

declare(strict_types=1);

namespace Araz\MicroService\Tests\Consumer\Processor\Command;

use Araz\MicroService\Processors\Command;
use Araz\MicroService\Processors\RequestResponse\Request;
use Araz\MicroService\Processors\RequestResponse\Response;
use Araz\MicroService\Serializers\JsonSerializer;
use Araz\MicroService\Serializers\PhpSerializer;

final class UserGetInfoCommandSerializer extends Command
{
    public function execute(Request $request): Response
    {
        $this->getQueue()->setDefaultSerializer(PhpSerializer::class);
        return new Response($request->getBody());
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
