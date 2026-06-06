<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;

/**
 * Keyset (cursor) pagination request carrying the incoming cursor and the page size.
 */
final readonly class CursorPagination implements Paging
{
    private function __construct(private int $limit, private Cursor $cursor)
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
        if ($perPage < 1) {
            throw PageSizeOutOfRange::belowMinimum(perPage: $perPage);
        }

        return new CursorPagination(limit: $perPage, cursor: $cursor);
    }

    public function limit(): int
    {
        return $this->limit;
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

    public function toQueryString(Schema $schema): string
    {
        $template = '%s=%d';
        $perPage = sprintf($template, $schema->perPageKey(), $this->limit);
        $token = $this->cursor->toString();

        if ($token === '') {
            return $perPage;
        }

        $template = '%s=%s&%s';

        return sprintf($template, $schema->cursorKey(), $token, $perPage);
    }
}
