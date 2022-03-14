<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors\RequestResponse;

use Araz\MicroService\Interfaces\RequestInterface;

class Request implements RequestInterface
{
    public function __construct(
        private string $messageId,
        private mixed $body,
        private int $time,
    ) {
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getTime(): int
    {
        return $this->time;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }
}
