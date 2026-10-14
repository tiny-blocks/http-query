<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Offset;

use TinyBlocks\HttpQuery\Exceptions\OffsetOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageNumberOutOfRange;
use TinyBlocks\HttpQuery\Internal\Limit;

final readonly class Offset
{
    private const int MAXIMUM = (PHP_INT_MAX - 1);

    private function __construct(private int $value)
    {
    }

    public static function from(int $value): Offset
    {
        if ($value < 0) {
            throw OffsetOutOfRange::from(offset: $value);
        }

        if ($value > Offset::MAXIMUM) {
            throw OffsetOutOfRange::aboveMaximum(offset: $value, maximum: Offset::MAXIMUM);
        }

        return new Offset(value: $value);
    }

    public static function fromPage(PageNumber $page, Limit $limit): Offset
    {
        $lastPage = (intdiv(Offset::MAXIMUM, $limit->value()) + 1);

        if ($page->value() > $lastPage) {
            throw PageNumberOutOfRange::aboveMaximum(page: $page->value(), maximum: $lastPage);
        }

        return new Offset(value: (($page->value() - 1) * $limit->value()));
    }

    public function value(): int
    {
        return $this->value;
    }
}
