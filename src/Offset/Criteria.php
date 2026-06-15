<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Offset;

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\FilterExpressionIsInvalid;
use TinyBlocks\HttpQuery\Exceptions\FilterFieldNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\FilterOperatorNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\FilterShapeNotSupported;
use TinyBlocks\HttpQuery\Exceptions\FilterValueNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\PageNumberOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\SortExpressionIsInvalid;
use TinyBlocks\HttpQuery\Exceptions\SortFieldNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\TotalIsNegative;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Internal\Query;
use TinyBlocks\HttpQuery\Schema;
use TinyBlocks\HttpQuery\Sort;

/**
 * Offset specification of a collection query, validated against the endpoint schema.
 *
 * <p>It parses the request query string and, against the schema, validates the filter into a
 * conjunction of comparisons and resolves the effective sort. It then builds the offset {@see Page}
 * or {@see Slice} the consumer renders. The pagination approach is fixed, never inferred, so every
 * page renders an offset self link.</p>
 */
final readonly class Criteria
{
    private function __construct(
        private Sort $sort,
        private Filter $filter,
        private Pagination $pagination,
        private array $comparisons,
        private Sort $submittedSort
    ) {
    }

    /**
     * Creates a Criteria from the request and an optional schema.
     *
     * <p>When the schema is omitted, an empty contract applies: the default page-size bounds, no
     * filterable or sortable field, and no default sort. Any incoming filter or sort is then
     * rejected. The pagination derives its offset from the one-based page number and the page size.</p>
     *
     * @param ServerRequestInterface $request The incoming PSR-7 server request.
     * @param Schema|null $schema The query contract, or null for the empty contract.
     * @return Criteria The criteria carrying the validated comparisons, the effective sort, and the pagination.
     * @throws FilterExpressionIsInvalid If the filter expression cannot be parsed.
     * @throws SortExpressionIsInvalid If the sort expression cannot be parsed.
     * @throws PageNumberOutOfRange If the page number is less than 1.
     * @throws PageSizeOutOfRange If the page size falls outside the valid range.
     * @throws FilterShapeNotSupported If the filter is not a comparison or an AND group of comparisons.
     * @throws FilterFieldNotAllowed If a comparison targets a field that was never allowed.
     * @throws FilterOperatorNotAllowed If a comparison uses an operator not allowed for its field.
     * @throws FilterValueNotAllowed If a compared value falls outside the permitted set or kind.
     * @throws SortFieldNotAllowed If the sort orders by a field that was never declared sortable.
     */
    public static function fromQuery(ServerRequestInterface $request, ?Schema $schema = null): Criteria
    {
        $query = Query::from(schema: $schema ?? Schema::default(), request: $request);

        return new Criteria(
            sort: $query->sort(),
            filter: $query->filter(),
            pagination: Pagination::fromPage(page: $query->pageNumber(), perPage: $query->pageSize()),
            comparisons: $query->comparisons(),
            submittedSort: $query->submittedSort()
        );
    }

    /**
     * Creates a Page from the total element count and the items.
     *
     * @template TValue
     * @param int $total The total element count across every page.
     * @param iterable<TValue> $items The items on the current page.
     * @return Page<TValue> The offset page carrying the items, the total, and the navigation.
     * @throws TotalIsNegative If the total is less than 0.
     */
    public function page(iterable $items, int $total): Page
    {
        return Page::from(
            sort: $this->submittedSort,
            items: $items,
            total: $total,
            filter: $this->filter,
            pagination: $this->pagination
        );
    }

    /**
     * Returns the effective sort.
     *
     * @return Sort The client sort when present, otherwise the schema default sort.
     */
    public function sort(): Sort
    {
        return $this->sort;
    }

    /**
     * Returns the page size.
     *
     * @return int The page size.
     */
    public function limit(): int
    {
        return $this->pagination->limit();
    }

    /**
     * Creates a Slice from the items, without a total element count.
     *
     * @template TValue
     * @param iterable<TValue> $items The items fetched for the page size plus one.
     * @return Slice<TValue> The offset slice carrying the trimmed items and the next-page hint.
     */
    public function slice(iterable $items): Slice
    {
        return Slice::from(
            sort: $this->submittedSort,
            items: $items,
            filter: $this->filter,
            pagination: $this->pagination
        );
    }

    /**
     * Returns the zero-based offset of the current page.
     *
     * @return int The offset of the current page.
     */
    public function offset(): int
    {
        return $this->pagination->offset();
    }

    /**
     * Returns the validated conjunction of comparisons.
     *
     * @return list<Comparison> The validated comparisons, an empty list when there is no filter.
     */
    public function comparisons(): array
    {
        return $this->comparisons;
    }
}
