<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors\RequestResponse;

use Araz\MicroService\Interfaces\RequestTopicInterface;

class RequestTopic extends Request implements RequestTopicInterface
{
    public function __construct(
        string $messageId,
        mixed $body,
        int $time,
        private mixed $routingKey,
    ) {
        parent::__construct($messageId, $body, $time);
    }

    public function getRoutingKey(): string
    {
        return $this->routingKey;
    }
}
