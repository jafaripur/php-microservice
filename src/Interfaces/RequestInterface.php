<?php

declare(strict_types=1);

namespace Araz\MicroService\Interfaces;

interface RequestInterface
{
    public function getMessageId(): string;

    public function getTime(): int;

    public function getBody(): mixed;
}
