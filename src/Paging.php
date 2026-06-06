<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Pagination request that knows its page size and how to serialize itself into a query string.
 *
 * <p>It is implemented by the offset-based {@see Pagination} and the keyset {@see CursorPagination},
 * so a {@see Criteria} carries either one without branching on the concrete type.</p>
 */
interface Paging
{
    /**
     * Returns the maximum number of elements per page.
     *
     * @return int The limit.
     */
    public function limit(): int;

    /**
     * Returns the pagination as a query string fragment built against the schema keys.
     *
     * @param Schema $schema The schema mapping the query key names.
     * @return string The query string fragment carrying the pagination.
     */
    public function toQueryString(Schema $schema): string;
}
