<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Offset;

use TinyBlocks\HttpQuery\Internal\Limit;
use TinyBlocks\HttpQuery\Offset\Pagination;

final readonly class OffsetNavigation
{
    private function __construct(
        private bool $hasNext,
        private Pagination $pagination,
        private PageNumber $pageNumber
    ) {
    }

    public static function from(bool $hasNext, Pagination $pagination): OffsetNavigation
    {
        $limit = Limit::from(value: $pagination->limit());
        $offset = Offset::from(value: $pagination->offset());

        return new OffsetNavigation(
            hasNext: $hasNext,
            pagination: $pagination,
            pageNumber: PageNumber::fromOffset(limit: $limit, offset: $offset)
        );
    }

    public function next(): ?Pagination
    {
        if (!$this->hasNext) {
            return null;
        }

        $next = $this->pageNumber->next();

        return Pagination::fromPage(page: $next->value(), perPage: $this->pagination->limit());
    }

    public function first(): Pagination
    {
        return Pagination::fromPage(page: 1, perPage: $this->pagination->limit());
    }

    public function limit(): int
    {
        return $this->pagination->limit();
    }

    public function atPage(int $page): Pagination
    {
        return Pagination::fromPage(page: $page, perPage: $this->pagination->limit());
    }

    public function offset(): int
    {
        return $this->pagination->offset();
    }

    public function hasNext(): bool
    {
        return $this->hasNext;
    }

    public function previous(): ?Pagination
    {
        $previous = $this->pageNumber->previous();

        return is_null($previous)
            ? null
            : Pagination::fromPage(page: $previous->value(), perPage: $this->pagination->limit());
    }

    public function currentPage(): int
    {
        return $this->pageNumber->value();
    }

    public function hasPrevious(): bool
    {
        return !$this->pageNumber->isFirst();
    }
}
