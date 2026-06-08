<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\Http\Attribute;
use TinyBlocks\Http\Server\Decoded\QueryParameters;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\CursorPagination;
use TinyBlocks\HttpQuery\OffsetPagination;
use TinyBlocks\HttpQuery\Pagination;
use TinyBlocks\HttpQuery\Schema;

final readonly class PageParameters
{
    private function __construct(private Cursor $cursor, private int $perPage, private int $pageNumber)
    {
    }

    public static function from(QueryParameters $query, Schema $schema): PageParameters
    {
        $page = $query->get(key: 'page')->toArray();

        $size = Attribute::from(value: $page['size'] ?? null);
        $cursor = Attribute::from(value: $page['cursor'] ?? null);
        $number = Attribute::from(value: $page['number'] ?? null);

        $requested = $size->toString() === '' ? $schema->defaultPerPage() : $size->toInteger();

        return new PageParameters(
            cursor: Cursor::from(token: $cursor->toString()),
            perPage: $schema->pageSizeFor(requested: $requested),
            pageNumber: $number->toString() === '' ? 1 : $number->toInteger()
        );
    }

    public function resolve(): Pagination
    {
        return $this->cursor->isAbsent() ? $this->toOffset() : $this->toCursor();
    }

    public function toCursor(): CursorPagination
    {
        return CursorPagination::from(cursor: $this->cursor, perPage: $this->perPage);
    }

    public function toOffset(): OffsetPagination
    {
        return OffsetPagination::fromPage(page: $this->pageNumber, perPage: $this->perPage);
    }
}
