<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Cursor;

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\FilterExpressionIsInvalid;
use TinyBlocks\HttpQuery\Exceptions\FilterFieldNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\FilterOperatorNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\FilterShapeNotSupported;
use TinyBlocks\HttpQuery\Exceptions\FilterValueNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\SortExpressionIsInvalid;
use TinyBlocks\HttpQuery\Exceptions\SortFieldNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\SortIsRequired;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Internal\Query;
use TinyBlocks\HttpQuery\Schema;
use TinyBlocks\HttpQuery\Sort;

/**
 * Cursor (keyset) specification of a collection query, validated against the endpoint schema.
 *
 * <p>It parses the request query string and, against the schema, validates the filter into a
 * conjunction of comparisons and resolves the effective sort. It then produces the {@see Keyset}
 * view the consumer drives to fetch and render a forward-only cursor page. The pagination approach
 * is fixed, never inferred, so a cursor page always renders a cursor self link.</p>
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
     * rejected. The pagination always carries the incoming cursor token and the page size.</p>
     *
     * @param ServerRequestInterface $request The incoming PSR-7 server request.
     * @param Schema|null $schema The query contract, or null for the empty contract.
     * @return Criteria The criteria carrying the validated comparisons, the effective sort, and the pagination.
     * @throws FilterExpressionIsInvalid If the filter expression cannot be parsed.
     * @throws SortExpressionIsInvalid If the sort expression cannot be parsed.
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
        $cursor = Token::from(token: $query->cursorToken());

        return new Criteria(
            sort: $query->sort(),
            filter: $query->filter(),
            pagination: Pagination::from(cursor: $cursor, perPage: $query->pageSize()),
            comparisons: $query->comparisons(),
            submittedSort: $query->submittedSort()
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
     * Returns the parsed filter tree.
     *
     * @return Filter The full filter tree, a comparison or a group of filters.
     */
    public function filter(): Filter
    {
        return $this->filter;
    }

    /**
     * Creates a Keyset cursor view ordered by the effective sort.
     *
     * <p>It pairs the effective sort with the validated filter and the cursor view. It works whether
     * an incoming cursor token is present, the first page yielding an empty cursor.</p>
     *
     * @return Keyset The cursor view carrying the seek inputs and the page builder.
     * @throws SortIsRequired If the effective sort is empty, so the keyset cannot anchor a page.
     */
    public function keyset(): Keyset
    {
        if ($this->sort->isEmpty()) {
            throw new SortIsRequired();
        }

        return Keyset::from(
            sort: $this->sort,
            filter: $this->filter,
            pagination: $this->pagination,
            submittedSort: $this->submittedSort
        );
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
