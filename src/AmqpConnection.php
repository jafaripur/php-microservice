<?php

declare(strict_types=1);

namespace Araz\MicroService;

use Enqueue\AmqpExt\AmqpConnectionFactory;
use Enqueue\AmqpBunny\AmqpConnectionFactory as AmqpBunnyConnectionFactory;
use Interop\Amqp\AmqpContext;

class AmqpConnection
{
    /**
     *
     * check for more: https://php-enqueue.github.io/transport
     * $transport => [
     *    'dsn' => 'amqps://guest:guest@localhost:5672/%2f',
     *    'ssl_cacert' => '/a/dir/cacert.pem',
     *    'ssl_cert' => '/a/dir/cert.pem',
     *    'ssl_key' => '/a/dir/key.pem',
     * ]
     *
     * @var AmqpContext|\Enqueue\AmqpBunny\AmqpContext $context
     */

    private $context;

    /**
     * Connection factory
     *
     * @var \Interop\Amqp\AmqpConnectionFactory
     */
    private $factory;

    /**
     *
     * @param  array       $transport
     * @param  string|null $amqpLibrary AmqpConnectionFactory|AmqpBunnyConnectionFactory
     */
    public function __construct(
        private array $transport,
        ?string $amqpLibrary = AmqpConnectionFactory::class,
    ) {
        if (!is_subclass_of($amqpLibrary, \Interop\Amqp\AmqpConnectionFactory::class)) {
            throw new \LogicException('The $amqpLibrary must be implement of \Interop\Amqp\AmqpConnectionFactory');
        }

        if ($amqpLibrary == AmqpConnectionFactory::class && !extension_loaded('amqp')) {
            throw new \LogicException('PHP Amqp extension not installed!');
        }

        if (!is_subclass_of($amqpLibrary, \Interop\Amqp\AmqpConnectionFactory::class)) {
            throw new \LogicException('The $amqpLibrary must be implement of \Interop\Amqp\AmqpConnectionFactory');
        }

        $this->context = (new $amqpLibrary($transport))->createContext();
    }

    /**
     * Get current context
     *
     * @return AmqpContext|\Enqueue\AmqpBunny\AmqpContext
     */
    public function getContext(): AmqpContext
    {
        return $this->context;
    }
}
