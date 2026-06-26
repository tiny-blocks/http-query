<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Offset;

use TinyBlocks\HttpQuery\Exceptions\TotalIsNegative;
use TinyBlocks\HttpQuery\Internal\Limit;

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
        return PageCount::from(limit: $limit, total: $this);
    }
}
