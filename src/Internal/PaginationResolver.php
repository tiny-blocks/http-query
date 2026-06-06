<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\Http\Server\Decoded\QueryParameters;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\CursorPagination;
use TinyBlocks\HttpQuery\Pagination;
use TinyBlocks\HttpQuery\Paging;
use TinyBlocks\HttpQuery\Schema;

final readonly class PaginationResolver
{
    private function __construct(private QueryParameters $query, private Schema $schema)
    {
    }

    public static function from(QueryParameters $query, Schema $schema): PaginationResolver
    {
        return new PaginationResolver(query: $query, schema: $schema);
    }

    public function resolve(): Paging
    {
        $attribute = $this->query->get(key: $this->schema->perPageKey());
        $requested = $attribute->toString() === '' ? $this->schema->defaultPerPage() : $attribute->toInteger();
        $perPage = $this->schema->pageSizeFor(requested: $requested);

        $cursorToken = $this->query->get(key: $this->schema->cursorKey())->toString();

        if ($cursorToken !== '') {
            return CursorPagination::from(cursor: Cursor::from(token: $cursorToken), perPage: $perPage);
        }

        $page = $this->query->get(key: $this->schema->pageKey());

        return Pagination::fromPage(page: $page->toString() === '' ? 1 : $page->toInteger(), perPage: $perPage);
    }
}
