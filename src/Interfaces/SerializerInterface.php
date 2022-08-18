<?php

declare(strict_types=1);

namespace Araz\MicroService\Interfaces;

interface SerializerInterface
{
    /**
     * Serialize data for sending to queue.
     */
    public function serialize(mixed $data): mixed;

    /**
     * Unserialize data for receiving from queue.
     */
    public function unserialize(mixed $data): mixed;

    /**
     * Serialize name for identify.
     */
    public function getName(): string;

    /**
     * content-type of encoded result.
     */
    public function getContentType(): string;
}
