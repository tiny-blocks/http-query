<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use Throwable;

/**
 * Common contract implemented by every exception raised by this library.
 *
 * Allows a single catch clause to handle any failure originating from query parsing or navigation
 * rendering, regardless of whether it stems from a filter expression, a sort expression, a
 * pagination invariant, or a cursor token.
 */
interface HttpQueryException extends Throwable
{
}
