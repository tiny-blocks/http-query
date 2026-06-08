<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Offset;

use TinyBlocks\HttpQuery\Exceptions\PageNumberOutOfRange;
use TinyBlocks\HttpQuery\Internal\Limit;

final readonly class PageNumber
{
    private function __construct(private int $value)
    {
    }

    public static function from(int $value): PageNumber
    {
        if ($value < 1) {
            throw PageNumberOutOfRange::from(page: $value);
        }

        return new PageNumber(value: $value);
    }

    public static function fromOffset(Offset $offset, Limit $limit): PageNumber
    {
        return new PageNumber(value: intdiv($offset->value(), $limit->value()) + 1);
    }

    public function next(): PageNumber
    {
        return new PageNumber(value: $this->value + 1);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isFirst(): bool
    {
        return $this->value === 1;
    }

    public function previous(): ?PageNumber
    {
        return $this->isFirst() ? null : new PageNumber(value: $this->value - 1);
    }
}
