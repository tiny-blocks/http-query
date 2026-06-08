<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;

/**
 * Page-size bounds used to read and write a Criteria.
 *
 * <p>The query parameter names follow JSON:API and are fixed: <code>filter</code>, <code>sort</code>,
 * and the <code>page</code> family. The default page size is 20 and the maximum is 100.</p>
 */
final readonly class Schema
{
    private function __construct(private int $maxPerPage, private int $defaultPerPage)
    {
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
     * Creates a Schema carrying the default page-size bounds.
     *
     * @return Schema The default schema.
     */
    public static function default(): Schema
    {
        return new Schema(maxPerPage: 100, defaultPerPage: 20);
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
        return new Schema(maxPerPage: $maxPerPage, defaultPerPage: $this->defaultPerPage);
    }

    /**
     * Returns a copy of the Schema with the default page size replaced.
     *
     * @param int $defaultPerPage The page size applied when the query omits one.
     * @return Schema A copy of the schema carrying the new default page size.
     */
    public function withDefaultPerPage(int $defaultPerPage): Schema
    {
        return new Schema(maxPerPage: $this->maxPerPage, defaultPerPage: $defaultPerPage);
    }
}
