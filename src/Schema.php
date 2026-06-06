<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;

/**
 * Configurable mapping of query parameter names and page-size bounds used to read and write a Criteria.
 *
 * <p>The defaults follow the canonical conventions: <code>filter</code>, <code>sort</code>,
 * <code>page</code>, <code>per_page</code>, and <code>cursor</code>, with a default page size of
 * 20 and a maximum of 100.</p>
 */
final readonly class Schema
{
    private function __construct(
        private string $pageKey,
        private string $sortKey,
        private string $cursorKey,
        private string $filterKey,
        private int $maxPerPage,
        private string $perPageKey,
        private int $defaultPerPage
    ) {
        if ($maxPerPage < 1) {
            throw PageSizeOutOfRange::belowMinimum(perPage: $maxPerPage);
        }

        if ($defaultPerPage < 1) {
            throw PageSizeOutOfRange::belowMinimum(perPage: $defaultPerPage);
        }

        if ($defaultPerPage > $maxPerPage) {
            throw PageSizeOutOfRange::aboveMaximum(maximum: $maxPerPage, perPage: $defaultPerPage);
        }
    }

    /**
     * Creates a Schema carrying the canonical default key names and bounds.
     *
     * @return Schema The default schema.
     */
    public static function default(): Schema
    {
        return new Schema(
            pageKey: 'page',
            sortKey: 'sort',
            cursorKey: 'cursor',
            filterKey: 'filter',
            maxPerPage: 100,
            perPageKey: 'per_page',
            defaultPerPage: 20
        );
    }

    /**
     * Returns the query key carrying the page number.
     *
     * @return string The page key.
     */
    public function pageKey(): string
    {
        return $this->pageKey;
    }

    /**
     * Returns the query key carrying the sort expression.
     *
     * @return string The sort key.
     */
    public function sortKey(): string
    {
        return $this->sortKey;
    }

    /**
     * Returns the query key carrying the cursor token.
     *
     * @return string The cursor key.
     */
    public function cursorKey(): string
    {
        return $this->cursorKey;
    }

    /**
     * Returns the query key carrying the RSQL filter.
     *
     * @return string The filter key.
     */
    public function filterKey(): string
    {
        return $this->filterKey;
    }

    /**
     * Returns the maximum allowed page size.
     *
     * @return int The maximum page size.
     */
    public function maxPerPage(): int
    {
        return $this->maxPerPage;
    }

    /**
     * Returns the query key carrying the page size.
     *
     * @return string The page-size key.
     */
    public function perPageKey(): string
    {
        return $this->perPageKey;
    }

    /**
     * Returns the requested page size bounded by the maximum allowed.
     *
     * @param int $requested The requested page size.
     * @return int The requested page size when it is within the maximum.
     * @throws PageSizeOutOfRange If the requested size exceeds the maximum.
     */
    public function pageSizeFor(int $requested): int
    {
        if ($requested > $this->maxPerPage) {
            throw PageSizeOutOfRange::aboveMaximum(maximum: $this->maxPerPage, perPage: $requested);
        }

        return $requested;
    }

    /**
     * Returns a copy of the Schema with the page key replaced.
     *
     * @param string $pageKey The query key carrying the page number.
     * @return Schema A copy of the schema carrying the new page key.
     */
    public function withPageKey(string $pageKey): Schema
    {
        return new Schema(
            pageKey: $pageKey,
            sortKey: $this->sortKey,
            cursorKey: $this->cursorKey,
            filterKey: $this->filterKey,
            maxPerPage: $this->maxPerPage,
            perPageKey: $this->perPageKey,
            defaultPerPage: $this->defaultPerPage
        );
    }

    /**
     * Returns a copy of the Schema with the sort key replaced.
     *
     * @param string $sortKey The query key carrying the sort expression.
     * @return Schema A copy of the schema carrying the new sort key.
     */
    public function withSortKey(string $sortKey): Schema
    {
        return new Schema(
            pageKey: $this->pageKey,
            sortKey: $sortKey,
            cursorKey: $this->cursorKey,
            filterKey: $this->filterKey,
            maxPerPage: $this->maxPerPage,
            perPageKey: $this->perPageKey,
            defaultPerPage: $this->defaultPerPage
        );
    }

    /**
     * Returns a copy of the Schema with the cursor key replaced.
     *
     * @param string $cursorKey The query key carrying the cursor token.
     * @return Schema A copy of the schema carrying the new cursor key.
     */
    public function withCursorKey(string $cursorKey): Schema
    {
        return new Schema(
            pageKey: $this->pageKey,
            sortKey: $this->sortKey,
            cursorKey: $cursorKey,
            filterKey: $this->filterKey,
            maxPerPage: $this->maxPerPage,
            perPageKey: $this->perPageKey,
            defaultPerPage: $this->defaultPerPage
        );
    }

    /**
     * Returns a copy of the Schema with the filter key replaced.
     *
     * @param string $filterKey The query key carrying the RSQL filter.
     * @return Schema A copy of the schema carrying the new filter key.
     */
    public function withFilterKey(string $filterKey): Schema
    {
        return new Schema(
            pageKey: $this->pageKey,
            sortKey: $this->sortKey,
            cursorKey: $this->cursorKey,
            filterKey: $filterKey,
            maxPerPage: $this->maxPerPage,
            perPageKey: $this->perPageKey,
            defaultPerPage: $this->defaultPerPage
        );
    }

    /**
     * Returns the page size applied when the query omits one.
     *
     * @return int The default page size.
     */
    public function defaultPerPage(): int
    {
        return $this->defaultPerPage;
    }

    /**
     * Returns a copy of the Schema with the maximum page size replaced.
     *
     * @param int $maxPerPage The maximum allowed page size.
     * @return Schema A copy of the schema carrying the new maximum page size.
     */
    public function withMaxPerPage(int $maxPerPage): Schema
    {
        return new Schema(
            pageKey: $this->pageKey,
            sortKey: $this->sortKey,
            cursorKey: $this->cursorKey,
            filterKey: $this->filterKey,
            maxPerPage: $maxPerPage,
            perPageKey: $this->perPageKey,
            defaultPerPage: $this->defaultPerPage
        );
    }

    /**
     * Returns a copy of the Schema with the page-size key replaced.
     *
     * @param string $perPageKey The query key carrying the page size.
     * @return Schema A copy of the schema carrying the new page-size key.
     */
    public function withPerPageKey(string $perPageKey): Schema
    {
        return new Schema(
            pageKey: $this->pageKey,
            sortKey: $this->sortKey,
            cursorKey: $this->cursorKey,
            filterKey: $this->filterKey,
            maxPerPage: $this->maxPerPage,
            perPageKey: $perPageKey,
            defaultPerPage: $this->defaultPerPage
        );
    }

    /**
     * Returns a copy of the Schema with the default page size replaced.
     *
     * @param int $defaultPerPage The page size applied when the query omits one.
     * @return Schema A copy of the schema carrying the new default page size.
     */
    public function withDefaultPerPage(int $defaultPerPage): Schema
    {
        return new Schema(
            pageKey: $this->pageKey,
            sortKey: $this->sortKey,
            cursorKey: $this->cursorKey,
            filterKey: $this->filterKey,
            maxPerPage: $this->maxPerPage,
            perPageKey: $this->perPageKey,
            defaultPerPage: $defaultPerPage
        );
    }
}
