<?php

declare(strict_types=1);

namespace Araz\MicroService\Processors\RequestResponse;

final class Response
{
    public function __construct(
        private mixed $body,
    ) {
    }

    public function getBody(): mixed
    {
        return $this->body;
    }
}
