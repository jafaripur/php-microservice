<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Enqueue\AmqpExt\AmqpConnectionFactory;
use Interop\Amqp\AmqpContext;
use Interop\Queue\Context;

class AmqpConnection
{
    /**
     * @var AmqpContext|Context
     */
    private Context $context;

    /**
     * check for more: https://php-enqueue.github.io/transport
     * $transport => [
     *    'dsn' => 'amqps://guest:guest@localhost:5672/%2f',
     *    'ssl_cacert' => '/a/dir/cacert.pem',
     *    'ssl_cert' => '/a/dir/cert.pem',
     *    'ssl_key' => '/a/dir/key.pem',
     * ].
     *
     * @param string $amqpLibrary AmqpConnectionFactory|AmqpBunnyConnectionFactory
     */
    public function __construct(
        array $transport,
        string $amqpLibrary = AmqpConnectionFactory::class,
    ) {
        if (!is_subclass_of($amqpLibrary, \Interop\Amqp\AmqpConnectionFactory::class)) {
            throw new \LogicException('The $amqpLibrary must be implement of \Interop\Amqp\AmqpConnectionFactory');
        }

        if (AmqpConnectionFactory::class == $amqpLibrary && !extension_loaded('amqp')) {
            throw new \LogicException('PHP Amqp extension not installed!');
        }

        if (!is_subclass_of($amqpLibrary, \Interop\Amqp\AmqpConnectionFactory::class)) {
            throw new \LogicException('The $amqpLibrary must be implement of \Interop\Amqp\AmqpConnectionFactory');
        }

        $this->context = (new $amqpLibrary($transport))->createContext();
    }

    public function getContext(): AmqpContext|Context
    {
        return $this->context;
    }
}
