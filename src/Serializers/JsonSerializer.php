<?php

declare(strict_types=1);

namespace Araz\MicroService\Serializers;

use Araz\MicroService\Interfaces\SerializerInterface;
use Yiisoft\Json\Json;

/**
 * Serialize and unserialize data with JSON.
 */
final class JsonSerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public function serialize(mixed $data): mixed
    {
        return Json::encode($data);
    }

    /**
     * @inheritDoc
     */
    public function unserialize(mixed $data): mixed
    {
        // @var string $data
        return Json::decode($data, true);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'json';
    }

    /**
     * @inheritDoc
     */
    public function getContentType(): string
    {
        return 'application/json';
    }
}
