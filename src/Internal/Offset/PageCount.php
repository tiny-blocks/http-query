<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Offset;

use TinyBlocks\HttpQuery\Internal\Limit;

final readonly class PageCount
{
    private function __construct(private int $value)
    {
    }

    public static function from(Limit $limit, Total $total): PageCount
    {
        $hasPartialPage = ($total->value() % $limit->value()) > 0;

        return new PageCount(value: (intdiv($total->value(), $limit->value()) + ($hasPartialPage ? 1 : 0)));
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === 0;
    }

    public function hasPageAfter(PageNumber $page): bool
    {
        return $page->value() < $this->value;
    }
}
