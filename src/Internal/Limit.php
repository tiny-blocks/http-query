<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;

final readonly class Limit
{
    private function __construct(private int $value)
    {
    }

    public static function from(int $value): Limit
    {
        if ($value < 1) {
            throw PageSizeOutOfRange::belowMinimum(perPage: $value);
        }

        return new Limit(value: $value);
    }

    public function value(): int
    {
        return $this->value;
    }
}
