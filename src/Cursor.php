<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;
use TinyBlocks\HttpQuery\Internal\CursorCodec;

/**
 * Opaque, URI-safe token wrapping the ordering key values of a keyset (cursor) page.
 *
 * <p>A cursor is built either from an incoming token (decoded on demand) or from the last-seen
 * ordering key values (encoded on demand). The token is the only form that travels in the query
 * string, the key values are the form the consumer feeds back to the store.</p>
 */
final readonly class Cursor
{
    private function __construct(private ?array $keys, private ?string $token)
    {
    }

    /**
     * Creates a Cursor wrapping an incoming opaque token, decoded on demand.
     *
     * @param string $token The opaque token received in the query string.
     * @return Cursor A Cursor backed by the incoming token.
     */
    public static function from(string $token): Cursor
    {
        return new Cursor(keys: null, token: $token);
    }

    /**
     * Creates an absent Cursor that carries neither a token nor key values.
     *
     * @return Cursor The absent cursor.
     */
    public static function none(): Cursor
    {
        return new Cursor(keys: null, token: null);
    }

    /**
     * Creates a Cursor from the last-seen ordering key values, encoded on demand.
     *
     * @param array<int|string, mixed> $keys The last-seen ordering key values.
     * @return Cursor A Cursor backed by the given key values.
     */
    public static function fromKeys(array $keys): Cursor
    {
        return new Cursor(keys: $keys, token: null);
    }

    /**
     * Returns the decoded ordering key values.
     *
     * @return array<int|string, mixed> The decoded key values, empty when the cursor is absent.
     * @throws CursorIsInvalid If the wrapped token cannot be decoded.
     */
    public function toArray(): array
    {
        if (!is_null($this->keys)) {
            return array_values($this->keys);
        }

        if (is_null($this->token) || $this->token === '') {
            return [];
        }

        return CursorCodec::decode(token: $this->token);
    }

    /**
     * Tells whether the cursor carries neither a token nor key values.
     *
     * @return bool True when the cursor is absent.
     */
    public function isAbsent(): bool
    {
        return match (true) {
            !is_null($this->keys) => false,
            is_null($this->token) => true,
            default               => $this->token === ''
        };
    }

    /**
     * Returns the opaque, URI-safe token.
     *
     * @return string The opaque token, empty when the cursor is absent.
     */
    public function toString(): string
    {
        if (!is_null($this->token)) {
            return $this->token;
        }

        if (is_null($this->keys)) {
            return '';
        }

        return CursorCodec::encode(keys: $this->keys);
    }
}
