<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Offset;

use TinyBlocks\HttpQuery\Internal\Limit;
use TinyBlocks\HttpQuery\OffsetPagination;

final readonly class OffsetNavigator
{
    private function __construct(private OffsetPagination $pagination)
    {
    }

    public static function from(OffsetPagination $pagination): OffsetNavigator
    {
        return new OffsetNavigator(pagination: $pagination);
    }

    public function next(): OffsetPagination
    {
        $next = $this->pageNumber()->next();

        return OffsetPagination::fromPage(page: $next->value(), perPage: $this->pagination->limit());
    }

    public function first(): OffsetPagination
    {
        return OffsetPagination::fromPage(page: 1, perPage: $this->pagination->limit());
    }

    public function atPage(int $page): OffsetPagination
    {
        return OffsetPagination::fromPage(page: $page, perPage: $this->pagination->limit());
    }

    public function isFirst(): bool
    {
        return $this->pageNumber()->isFirst();
    }

    public function previous(): ?OffsetPagination
    {
        $previous = $this->pageNumber()->previous();

        return is_null($previous)
            ? null
            : OffsetPagination::fromPage(page: $previous->value(), perPage: $this->pagination->limit());
    }

    private function pageNumber(): PageNumber
    {
        return PageNumber::fromOffset(
            offset: Offset::from(value: $this->pagination->offset()),
            limit: Limit::from(value: $this->pagination->limit())
        );
    }
}
