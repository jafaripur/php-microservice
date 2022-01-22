<?php

declare(strict_types=1);

namespace Araz\MicroService\Serializers;

use Araz\MicroService\Interfaces\SerializerInterface;

/**
 * Serialize and unserialize data with MessagePack
 *
 * https://github.com/msgpack/msgpack-php
 *
 */
final class MessagePackSerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public function serialize(mixed $data): mixed
    {
        return msgpack_pack($data);
    }

    /**
     * @inheritDoc
     */
    public function unserialize(mixed $data): mixed
    {
        /**
         * @var string $data
         */
        return msgpack_unpack($data);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'msgpack';
    }

    /**
     * @inheritDoc
     *
     * @return string
     */
    public function getContentType(): string
    {
        return 'application/octet-stream';
    }
}
