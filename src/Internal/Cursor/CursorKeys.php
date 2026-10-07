<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Cursor;

use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;
use TinyBlocks\HttpQuery\Order;
use TinyBlocks\HttpQuery\Sort;
use TinyBlocks\HttpQuery\ValueKind;

final readonly class CursorKeys
{
    private function __construct(private array $kinds)
    {
    }

    public static function createFromEmpty(): CursorKeys
    {
        return new CursorKeys(kinds: []);
    }

    private function fits(mixed $key, ValueKind $kind): bool
    {
        return is_null($key) || ((is_string($key) || is_int($key)) && $kind->matches(value: (string) $key));
    }

    public function with(ValueKind $kind, string $field): CursorKeys
    {
        return new CursorKeys(kinds: [...$this->kinds, $field => $kind]);
    }

    public function permit(Sort $sort, Token $cursor): Token
    {
        $fields = array_map(static fn(Order $order): string => $order->field(), $sort->orders());
        $keys = $cursor->keyedBy(fields: $fields);

        foreach ($fields as $field) {
            $kind = ($this->kinds[$field] ?? null);

            if (!is_null($kind) && !$this->fits(key: $keys[$field], kind: $kind)) {
                throw CursorIsInvalid::kindMismatch(kind: $kind, field: $field, token: $cursor->toString());
            }
        }

        return $cursor;
    }
}
