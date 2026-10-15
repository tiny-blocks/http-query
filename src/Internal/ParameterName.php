<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Exceptions\PreservedParameterIsInvalid;

final readonly class ParameterName
{
    private const array RESERVED = ['page', 'sort', 'filter'];

    private function __construct(public string $value)
    {
    }

    public static function from(string $value): ParameterName
    {
        if ($value === '' || strpbrk($value, '[]') !== false || in_array($value, self::RESERVED, true)) {
            throw PreservedParameterIsInvalid::fromName(name: $value);
        }

        return new ParameterName(value: $value);
    }
}
