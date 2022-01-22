<?php

declare(strict_types=1);

namespace Araz\MicroService\Serializers;

use Araz\MicroService\Interfaces\SerializerInterface;

/**
 * Serialize and unserialize data with PHP serialize
 */
final class PhpSerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public function serialize(mixed $data): mixed
    {
        return serialize($data);
    }

    /**
     * @inheritDoc
     */
    public function unserialize(mixed $data): mixed
    {
        /**
         * @var string $data
         */
        return unserialize($data);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'php';
    }

    /**
     * @inheritDoc
     *
     * @return string
     */
    public function getContentType(): string
    {
        return 'text/plain';
    }
}
