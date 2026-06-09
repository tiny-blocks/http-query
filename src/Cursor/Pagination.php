<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Cursor;

use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Internal\Limit;
use TinyBlocks\HttpQuery\Pagination as PaginationContract;

/**
 * Keyset (cursor) pagination request carrying the incoming cursor and the page size.
 */
final readonly class Pagination implements PaginationContract
{
    private function __construct(private Limit $limit, private Token $cursor)
    {
    }

    /**
     * Creates a Pagination from a cursor and a page size.
     *
     * @param Token $cursor The incoming cursor.
     * @param int $perPage The page size.
     * @return Pagination The pagination carrying the cursor and the limit.
     * @throws PageSizeOutOfRange If the page size is less than 1.
     */
    public static function from(Token $cursor, int $perPage): Pagination
    {
        return new Pagination(limit: Limit::from(value: $perPage), cursor: $cursor);
    }

    public function limit(): int
    {
        return $this->limit->value();
    }

    /**
     * Returns the incoming cursor.
     *
     * @return Token The cursor.
     */
    public function cursor(): Token
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
