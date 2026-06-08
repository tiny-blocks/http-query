<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Exceptions\OffsetOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageNumberOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Internal\Limit;
use TinyBlocks\HttpQuery\Internal\Offset\Offset;
use TinyBlocks\HttpQuery\Internal\Offset\PageNumber;

/**
 * Offset-based pagination request whose canonical state is an offset and a limit.
 *
 * <p>The page-based factory derives the offset from the one-based page number and the page size.</p>
 */
final readonly class OffsetPagination implements Pagination
{
    private function __construct(private Limit $limit, private Offset $offset)
    {
    }

    /**
     * Creates an OffsetPagination from a one-based page number and a page size.
     *
     * @param int $page The one-based page number.
     * @param int $perPage The page size.
     * @return OffsetPagination The pagination whose offset is derived from the page and the page size.
     * @throws PageNumberOutOfRange If the page number is less than 1.
     * @throws PageSizeOutOfRange If the page size is less than 1.
     */
    public static function fromPage(int $page, int $perPage): OffsetPagination
    {
        $limit = Limit::from(value: $perPage);
        $number = PageNumber::from(value: $page);

        return new OffsetPagination(limit: $limit, offset: Offset::fromPage(page: $number, limit: $limit));
    }

    /**
     * Creates an OffsetPagination from a zero-based offset and a limit.
     *
     * @param int $offset The zero-based offset.
     * @param int $limit The maximum number of elements per page.
     * @return OffsetPagination The pagination carrying the offset and the limit.
     * @throws OffsetOutOfRange If the offset is less than 0.
     * @throws PageSizeOutOfRange If the limit is less than 1.
     */
    public static function fromOffset(int $offset, int $limit): OffsetPagination
    {
        return new OffsetPagination(limit: Limit::from(value: $limit), offset: Offset::from(value: $offset));
    }

    /**
     * Returns the one-based page number derived from the offset and the limit.
     *
     * @return int The one-based page number.
     */
    public function page(): int
    {
        return PageNumber::fromOffset(offset: $this->offset, limit: $this->limit)->value();
    }

    public function limit(): int
    {
        return $this->limit->value();
    }

    /**
     * Returns the zero-based offset.
     *
     * @return int The offset.
     */
    public function offset(): int
    {
        return $this->offset->value();
    }

    public function toQueryString(): string
    {
        $template = 'page[number]=%d&page[size]=%d';

        return sprintf($template, $this->page(), $this->limit->value());
    }
}
