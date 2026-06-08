<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Exceptions\OffsetOutOfRange;

final readonly class Offset
{
    private function __construct(private int $value)
    {
    }

    public static function from(int $value): Offset
    {
        if ($value < 0) {
            throw OffsetOutOfRange::from(offset: $value);
        }

        return new Offset(value: $value);
    }

    public static function fromPage(PageNumber $page, Limit $limit): Offset
    {
        return new Offset(value: ($page->value() - 1) * $limit->value());
    }

    public function value(): int
    {
        return $this->value;
    }
}
