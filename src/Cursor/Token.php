<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Cursor;

use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;
use TinyBlocks\HttpQuery\Internal\Cursor\CursorCodec;

/**
 * Opaque, URI-safe token wrapping the ordering key values of a keyset (cursor) page.
 *
 * <p>A token is built either from an incoming string (decoded on demand) or from the last-seen
 * ordering key values (encoded on demand). The string is the only form that travels in the query
 * string, the key values are the form the consumer feeds back to the store.</p>
 */
final readonly class Token
{
    private function __construct(private ?array $keys, private ?string $token)
    {
    }

    /**
     * Creates a Token wrapping an incoming opaque string, decoded on demand.
     *
     * @param string $token The opaque token received in the query string.
     * @return Token A Token backed by the incoming string.
     */
    public static function from(string $token): Token
    {
        return new Token(keys: null, token: $token);
    }

    /**
     * Creates an absent Token that carries neither a string nor key values.
     *
     * @return Token The absent token.
     */
    public static function none(): Token
    {
        return new Token(keys: null, token: null);
    }

    /**
     * Creates a Token from the last-seen ordering key values, encoded on demand.
     *
     * @param array<int|string, mixed> $keys The last-seen ordering key values.
     * @return Token A Token backed by the given key values.
     */
    public static function fromKeys(array $keys): Token
    {
        return new Token(keys: $keys, token: null);
    }

    /**
     * Returns the decoded ordering key values keyed by the given field names.
     *
     * <p>Every field is present. The value is null for every field when the token is absent, that is
     * on the first page.</p>
     *
     * @param list<string> $fields The field names the decoded values are keyed by.
     * @return array<string, mixed> The decoded key values keyed by field name.
     * @throws CursorIsInvalid If the decoded value count does not match the number of fields.
     */
    public function keyedBy(array $fields): array
    {
        if ($this->isAbsent()) {
            return array_fill_keys($fields, null);
        }

        $values = $this->toArray();

        if (count($values) !== count($fields)) {
            throw CursorIsInvalid::from(token: $this->toString());
        }

        return array_combine($fields, $values);
    }

    /**
     * Returns the decoded ordering key values.
     *
     * @return array<int|string, mixed> The decoded key values, empty when the token is absent.
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
     * Tells whether the token carries neither a string nor key values.
     *
     * @return bool True when the token is absent.
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
     * @return string The opaque token, empty when the token is absent.
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
