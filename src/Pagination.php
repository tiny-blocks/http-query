<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Pagination request that knows its page size and how to serialize itself into a query string.
 *
 * <p>It is implemented by the offset-based {@see Offset\Pagination} and the
 * keyset {@see Cursor\Pagination}, so {@see Links} renders a page over either
 * approach without branching on the concrete type.</p>
 */
interface Pagination
{
    /**
     * Returns the maximum number of elements per page.
     *
     * @return int The limit.
     */
    public function limit(): int;

    /**
     * Returns the pagination as a JSON:API query string fragment.
     *
     * @return string The query string fragment carrying the pagination.
     */
    public function toQueryString(): string;
}
