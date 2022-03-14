<?php

declare(strict_types=1);

namespace Araz\MicroService\Interfaces;

interface RequestTopicInterface
{
    public function getRoutingKey(): string;
}
