<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Internal\Limit;

/**
 * Keyset (cursor) pagination request carrying the incoming cursor and the page size.
 */
final readonly class CursorPagination implements Pagination
{
    private function __construct(private Limit $limit, private Cursor $cursor)
    {
    }

    /**
     * Creates a CursorPagination from a cursor and a page size.
     *
     * @param Cursor $cursor The incoming cursor.
     * @param int $perPage The page size.
     * @return CursorPagination The pagination carrying the cursor and the limit.
     * @throws PageSizeOutOfRange If the page size is less than 1.
     */
    public static function from(Cursor $cursor, int $perPage): CursorPagination
    {
        return new CursorPagination(limit: Limit::from(value: $perPage), cursor: $cursor);
    }

    public function limit(): int
    {
        return $this->limit->value();
    }

    /**
     * Returns the incoming cursor.
     *
     * @return Cursor The cursor.
     */
    public function cursor(): Cursor
    {
        return $this->cursor;
    }

    public function toQueryString(): string
    {
        $template = 'page[size]=%d';
        $size = sprintf($template, $this->limit->value());
        $token = $this->cursor->toString();

        if ($token === '') {
            return $size;
        }

        $template = 'page[cursor]=%s&%s';

        return sprintf($template, $token, $size);
    }
}
