<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Exceptions\TotalIsNegative;

final readonly class Total
{
    private function __construct(private int $value)
    {
    }

    public static function from(int $value): Total
    {
        if ($value < 0) {
            throw TotalIsNegative::from(total: $value);
        }

        return new Total(value: $value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function pageCount(Limit $limit): PageCount
    {
        return PageCount::from(total: $this, limit: $limit);
    }
}
