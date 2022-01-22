<?php

declare(strict_types=1);

namespace Araz\MicroService\Exceptions;

/**
 * Indicates the timeout reached with the sent message.
 */
class CommandTimeoutException extends \BadMethodCallException implements \Throwable
{
}
