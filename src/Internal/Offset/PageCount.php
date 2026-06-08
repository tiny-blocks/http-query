<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Offset;

use TinyBlocks\HttpQuery\Internal\Limit;

final readonly class PageCount
{
    private function __construct(private int $value)
    {
    }

    public static function from(Total $total, Limit $limit): PageCount
    {
        return new PageCount(value: (int)ceil($total->value() / $limit->value()));
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
