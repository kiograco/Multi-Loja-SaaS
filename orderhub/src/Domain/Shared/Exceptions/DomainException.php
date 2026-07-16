<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared\Exceptions;

use RuntimeException;

/**
 * Root of the domain exception hierarchy. Every domain-specific failure
 * extends this so infrastructure can map them to HTTP 4xx responses.
 */
abstract class DomainException extends RuntimeException
{
}
