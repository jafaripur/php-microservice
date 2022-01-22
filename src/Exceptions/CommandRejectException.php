<?php

declare(strict_types=1);

namespace Araz\MicroService\Exceptions;

/**
 * Indicates the reject of the sent message.
 */
class CommandRejectException extends \InvalidArgumentException implements \Throwable
{
}
