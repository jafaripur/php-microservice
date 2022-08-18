<?php

declare(strict_types=1);

namespace Araz\MicroService\Serializers;

use Araz\MicroService\Interfaces\SerializerInterface;

/**
 * Serialize and unserialize data with Igbinary.
 */
final class IgbinarySerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public function serialize(mixed $data): mixed
    {
        return igbinary_serialize($data);
    }

    /**
     * @inheritDoc
     */
    public function unserialize(mixed $data): mixed
    {
        // @var string $data
        return igbinary_unserialize($data);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'igbinary';
    }

    /**
     * @inheritDoc
     */
    public function getContentType(): string
    {
        return 'application/octet-stream';
    }
}
