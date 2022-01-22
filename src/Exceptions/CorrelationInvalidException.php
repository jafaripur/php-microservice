<?php

declare(strict_types=1);

namespace Araz\MicroService\Exceptions;

/**
 * Represents command received correlation is invalid.
 */
class CorrelationInvalidException extends \InvalidArgumentException implements \Throwable
{
}
