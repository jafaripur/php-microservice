<?php

declare(strict_types=1);

namespace Araz\MicroService\Exceptions;

/**
 * Represents seiralized of received message not support
 */
class SerializerNotFoundException extends \InvalidArgumentException implements \Throwable
{
}
