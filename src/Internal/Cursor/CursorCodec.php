<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Cursor;

use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;

final class CursorCodec
{
    private function __construct()
    {
    }

    public static function decode(string $token): array
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        if ($decoded === false) {
            throw CursorIsInvalid::from(token: $token);
        }

        $keys = json_decode($decoded);

        if (!is_array($keys)) {
            throw CursorIsInvalid::from(token: $token);
        }

        return $keys;
    }

    public static function encode(array $keys): string
    {
        $payload = json_encode($keys, JSON_THROW_ON_ERROR);

        return $payload
                |> base64_encode(...)
                |> (static fn(string $encoded): string => strtr($encoded, '+/', '-_'))
                |> (static fn(string $encoded): string => rtrim($encoded, '='));
    }
}
