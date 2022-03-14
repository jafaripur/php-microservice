<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors\RequestResponse;

final class ResponseAsync
{
    public function __construct(
        private string $ack,
        private mixed $body,
    ) {
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function getAck(): string
    {
        return $this->ack;
    }
}
