<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Interop\Amqp\Impl\AmqpMessage;

final class MessageProperty
{
    private const QUEUE_MESSAGE_PROPERTY_TOPIC = 'araz_topic';
    private const QUEUE_MESSAGE_PROPERTY_QUEUE = 'araz_queue';
    private const QUEUE_MESSAGE_PROPERTY_JOB = 'araz_job';
    private const QUEUE_MESSAGE_PROPERTY_METHOD = 'araz_method';
    private const QUEUE_MESSAGE_PROPERTY_SERIALIZE = 'araz_serialize';
    private const QUEUE_MESSAGE_PROPERTY_STATUS = 'araz_ack_status';
    private const QUEUE_MESSAGE_PROPERTY_REDELIVER_KEY = 'araz_redelivered_count';

    /**
     * Get property data
     *
     * @param  AmqpMessage $message
     * @param  string      $key
     * @param  mixed       $default
     * @return mixed
     */
    public static function getProperty(AmqpMessage $message, string $key, ?string $default = null): ?string
    {
        // https://php-enqueue.github.io/transport/amqp/ payload in AMQP server is not same as https://php-enqueue.github.io/transport/amqp_bunny/

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

    public static function setSerializer(AmqpMessage $message, string $value): void
    {
        $message->setProperty(self::QUEUE_MESSAGE_PROPERTY_SERIALIZE, $value);
    }

    public static function getSerializer(AmqpMessage $message, ?string $default = null): ?string
    {
        return static::getProperty($message, self::QUEUE_MESSAGE_PROPERTY_SERIALIZE, $default);
    }

    public static function setMethod(AmqpMessage $message, string $value): void
    {
        $message->setProperty(self::QUEUE_MESSAGE_PROPERTY_METHOD, $value);
    }

    public static function getMethod(AmqpMessage $message, ?string $default = null): ?string
    {
        return static::getProperty($message, self::QUEUE_MESSAGE_PROPERTY_METHOD, $default);
    }

    public static function setJob(AmqpMessage $message, string $value): void
    {
        $message->setProperty(self::QUEUE_MESSAGE_PROPERTY_JOB, $value);
    }

    public static function getJob(AmqpMessage $message, ?string $default = null): ?string
    {
        return static::getProperty($message, self::QUEUE_MESSAGE_PROPERTY_JOB, $default);
    }

    public static function setTopic(AmqpMessage $message, string $value): void
    {
        $message->setProperty(self::QUEUE_MESSAGE_PROPERTY_TOPIC, $value);
    }

    public static function getTopic(AmqpMessage $message, ?string $default = null): ?string
    {
        return static::getProperty($message, self::QUEUE_MESSAGE_PROPERTY_TOPIC, $default);
    }

    public static function setQueue(AmqpMessage $message, string $value): void
    {
        $message->setProperty(self::QUEUE_MESSAGE_PROPERTY_QUEUE, $value);
    }

    public static function getQueue(AmqpMessage $message, ?string $default = null): ?string
    {
        return static::getProperty($message, self::QUEUE_MESSAGE_PROPERTY_QUEUE, $default);
    }

    public static function setStatus(AmqpMessage $message, string $value): void
    {
        $message->setProperty(self::QUEUE_MESSAGE_PROPERTY_STATUS, $value);
    }

    public static function getStatus(AmqpMessage $message, ?string $default = null): ?string
    {
        return static::getProperty($message, self::QUEUE_MESSAGE_PROPERTY_STATUS, $default);
    }

    public static function setRedeliver(AmqpMessage $message, string $value): void
    {
        $message->setProperty(self::QUEUE_MESSAGE_PROPERTY_REDELIVER_KEY, $value);
    }

    public static function getRedeliver(AmqpMessage $message, ?string $default = null): ?string
    {
        return static::getProperty($message, self::QUEUE_MESSAGE_PROPERTY_REDELIVER_KEY, $default);
    }
}
