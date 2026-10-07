<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;
use Throwable;
use TinyBlocks\HttpQuery\ValueKind;

/**
 * Raised when an opaque cursor token cannot be decoded back into its ordering key values.
 *
 * A cursor token is produced by the library and must round-trip through its codec. A token that
 * was truncated, tampered with, or generated elsewhere fails to decode. A token that decodes but
 * carries a value outside the kind declared for its cursor key is rejected the same way.
 */
final class CursorIsInvalid extends InvalidArgumentException implements HttpQueryException
{
    private const string KIND_MISMATCH = 'Cursor token <%s> does not match the %s kind for cursor key <%s>.';
    private const string NOT_DECODABLE = 'Cursor token <%s> is invalid and could not be decoded.';

    private function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct(message: $reason, previous: $previous);
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
        $template = CursorIsInvalid::NOT_DECODABLE;

        return new CursorIsInvalid(reason: sprintf($template, $token), previous: $previous);
    }

    /**
     * Creates a CursorIsInvalid signaling that a decoded value does not match its cursor key kind.
     *
     * @param ValueKind $kind The value kind declared for the cursor key.
     * @param string $field The cursor key whose value was rejected.
     * @param string $token The opaque cursor token carrying the rejected value.
     * @return CursorIsInvalid The composed exception describing the kind mismatch.
     */
    public static function kindMismatch(ValueKind $kind, string $field, string $token): CursorIsInvalid
    {
        $template = CursorIsInvalid::KIND_MISMATCH;

        return new CursorIsInvalid(reason: sprintf($template, $token, $kind->value, $field));
    }
}
