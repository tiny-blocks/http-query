<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;
use Throwable;

/**
 * Raised when an opaque cursor token cannot be decoded back into its ordering key values.
 *
 * A cursor token is produced by the library and must round-trip through its codec. A token that
 * was truncated, tampered with, or generated elsewhere fails to decode.
 */
final class CursorIsInvalid extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON_TEMPLATE = 'Cursor token <%s> is invalid and could not be decoded.';

    private function __construct(string $token, ?Throwable $previous)
    {
        $template = CursorIsInvalid::REASON_TEMPLATE;

        parent::__construct(message: sprintf($template, $token), previous: $previous);
    }

    /**
     * Creates a CursorIsInvalid from the offending token and the optional underlying cause.
     *
     * @param string $token The opaque cursor token that could not be decoded.
     * @param Throwable|null $previous The underlying decoding failure preserved in the chain, if any.
     * @return CursorIsInvalid The composed exception describing the invalid cursor token.
     */
    public static function from(string $token, ?Throwable $previous = null): CursorIsInvalid
    {
        return new CursorIsInvalid(token: $token, previous: $previous);
    }
}
