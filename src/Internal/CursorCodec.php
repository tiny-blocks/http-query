<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\Encoder\Base62;
use TinyBlocks\Encoder\Internal\Exceptions\InvalidDecoding;
use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;

final class CursorCodec
{
    private function __construct()
    {
    }

    public static function decode(string $token): array
    {
        try {
            $payload = Base62::from(value: $token)->decode();
        } catch (InvalidDecoding $exception) {
            throw CursorIsInvalid::from(token: $token, previous: $exception);
        }

        $keys = json_decode($payload);

        if (!is_array($keys)) {
            throw CursorIsInvalid::from(token: $token);
        }

        return $keys;
    }

    public static function encode(array $keys): string
    {
        $payload = json_encode($keys, JSON_THROW_ON_ERROR);

        return Base62::from(value: $payload)->encode();
    }
}
