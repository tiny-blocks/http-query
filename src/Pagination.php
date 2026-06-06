<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Exceptions\OffsetOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageNumberOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;

/**
 * Offset-based pagination request whose canonical state is an offset and a limit.
 *
 * <p>The page-based factory derives the offset from the one-based page number and the page size.</p>
 */
final readonly class Pagination implements Paging
{
    private function __construct(private int $offset, private int $limit)
    {
    }

    /**
     * Creates a Pagination from a one-based page number and a page size.
     *
     * @param int $page The one-based page number.
     * @param int $perPage The page size.
     * @return Pagination The pagination whose offset is derived from the page and the page size.
     * @throws PageNumberOutOfRange If the page number is less than 1.
     * @throws PageSizeOutOfRange If the page size is less than 1.
     */
    public static function fromPage(int $page, int $perPage): Pagination
    {
        if ($page < 1) {
            throw PageNumberOutOfRange::from(page: $page);
        }

        if ($perPage < 1) {
            throw PageSizeOutOfRange::belowMinimum(perPage: $perPage);
        }

        return new Pagination(offset: ($page - 1) * $perPage, limit: $perPage);
    }

    /**
     * Creates a Pagination from a zero-based offset and a limit.
     *
     * @param int $offset The zero-based offset.
     * @param int $limit The maximum number of elements per page.
     * @return Pagination The pagination carrying the offset and the limit.
     * @throws OffsetOutOfRange If the offset is less than 0.
     * @throws PageSizeOutOfRange If the limit is less than 1.
     */
    public static function fromOffset(int $offset, int $limit): Pagination
    {
        if ($offset < 0) {
            throw OffsetOutOfRange::from(offset: $offset);
        }

        if ($limit < 1) {
            throw PageSizeOutOfRange::belowMinimum(perPage: $limit);
        }

        return new Pagination(offset: $offset, limit: $limit);
    }

    /**
     * Returns the pagination for the next page.
     *
     * @return Pagination The pagination for the next page.
     */
    public function next(): Pagination
    {
        return Pagination::fromPage(page: $this->page() + 1, perPage: $this->limit);
    }

    /**
     * Returns the one-based page number derived from the offset and the limit.
     *
     * @return int The one-based page number.
     */
    public function page(): int
    {
        return intdiv($this->offset, $this->limit) + 1;
    }

    /**
     * Returns the pagination for the first page.
     *
     * @return Pagination The pagination for the first page.
     */
    public function first(): Pagination
    {
        return Pagination::fromPage(page: 1, perPage: $this->limit);
    }

    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * Returns the pagination for the given page.
     *
     * @param int $page The one-based page number.
     * @return Pagination The pagination for the given page.
     * @throws PageNumberOutOfRange If the page number is less than 1.
     */
    public function atPage(int $page): Pagination
    {
        return Pagination::fromPage(page: $page, perPage: $this->limit);
    }

    /**
     * Returns the zero-based offset.
     *
     * @return int The offset.
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Returns the pagination for the previous page, or null when there is none.
     *
     * @return Pagination|null The pagination for the previous page, or null.
     */
    public function previous(): ?Pagination
    {
        if (!$this->hasPrevious()) {
            return null;
        }

        return Pagination::fromPage(page: $this->page() - 1, perPage: $this->limit);
    }

    /**
     * Tells whether a previous page exists.
     *
     * @return bool True when a previous page exists.
     */
    public function hasPrevious(): bool
    {
        return $this->page() > 1;
    }

    public function toQueryString(Schema $schema): string
    {
        $template = '%s=%d&%s=%d';

        return sprintf($template, $schema->pageKey(), $this->page(), $schema->perPageKey(), $this->limit);
    }
}
