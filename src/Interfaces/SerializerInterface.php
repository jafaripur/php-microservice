<?php

declare(strict_types=1);

namespace Araz\MicroService\Interfaces;

interface SerializerInterface
{
    /**
     * Serialize data for sending to queue
     *
     * @param  mixed $data
     * @return mixed
     */
    public function serialize(mixed $data): mixed;

    /**
     * Unserialize data for receiving from queue
     *
     * @param  mixed $data
     * @return mixed
     */
    public function unserialize(mixed $data): mixed;

    /**
     * Serialize name for identify
     *
     * @return string
     */
    public function getName(): string;

    /**
     * content-type of encoded result
     *
     * @return string
     */
    public function getContentType(): string;
}
