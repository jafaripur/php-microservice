<?php

declare(strict_types=1);

namespace Araz\MicroService;

//use Interop\Amqp\AmqpMessage;
use Interop\Amqp\Impl\AmqpMessage;

final class MessageProperty
{
    /**
     * Get property data
     *
     * @param  AmqpMessage $message
     * @param  string      $key
     * @param  mixed       $default
     * @return mixed
     */
    public static function getProperty(AmqpMessage $message, string $key, mixed $default = null): mixed
    {
        $data = $message->getProperty($key, null) ?? $message->getProperty('application_headers', [])[$key] ?? null;
        return $data ?? $message->getHeader($key, $default);
    }

    /**
     * Set property for message
     *
     * @param  AmqpMessage $message
     * @param  string      $key
     * @param  mixed       $value
     * @return void
     */
    public static function setProperty(AmqpMessage $message, string $key, mixed $value): void
    {
        $message->setProperty($key, $value);
    }
}
