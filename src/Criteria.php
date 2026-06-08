<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\Http\Server\Decoded\QueryParameters;
use TinyBlocks\HttpQuery\Exceptions\FilterExpressionIsInvalid;
use TinyBlocks\HttpQuery\Exceptions\OffsetOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageNumberOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\SortExpressionIsInvalid;
use TinyBlocks\HttpQuery\Exceptions\TotalIsNegative;
use TinyBlocks\HttpQuery\Internal\PageParameters;
use TinyBlocks\HttpQuery\Internal\Rsql\FilterParser;

/**
 * Umbrella specification of a collection query, pairing filtering, sorting, and pagination.
 *
 * <p>It parses from the request query string and serializes back to a URI, so the navigation links
 * built from it preserve the filter and the sort. It also builds the result page for the items the
 * store returns, keeping the pagination style internal.</p>
 */
final readonly class Criteria
{
    private function __construct(
        private PageParameters $page,
        private Sort $sort,
        private Filter $filter,
        private Pagination $pagination
    ) {
    }

    /**
     * Creates a Criteria from the request and an optional schema.
     *
     * <p>When the schema is omitted, the default page-size bounds apply. The pagination is a
     * {@see CursorPagination} when the cursor is present, otherwise an {@see OffsetPagination}.</p>
     *
     * @param ServerRequestInterface $request The incoming PSR-7 server request.
     * @param Schema|null $schema The schema carrying the page-size bounds, or null for the default.
     * @return Criteria The criteria carrying the parsed filter, sort, and pagination.
     * @throws FilterExpressionIsInvalid If the filter expression cannot be parsed.
     * @throws SortExpressionIsInvalid If the sort expression cannot be parsed.
     * @throws PageNumberOutOfRange If the page number is less than 1.
     * @throws PageSizeOutOfRange If the page size falls outside the valid range.
     * @throws OffsetOutOfRange If the offset is less than 0.
     */
    public static function fromQuery(ServerRequestInterface $request, ?Schema $schema = null): Criteria
    {
        $schema = $schema ?? Schema::default();
        $query = QueryParameters::from(request: $request);
        $page = PageParameters::from(query: $query, schema: $schema);

        $expression = $query->get(key: 'filter')->toString();
        $filter = $expression === '' ? Group::none() : FilterParser::from(input: $expression)->parse();
        $sort = Sort::fromExpression(expression: $query->get(key: 'sort')->toString());

        return new Criteria(page: $page, sort: $sort, filter: $filter, pagination: $page->resolve());
    }

    /**
     * Returns the criteria as a URI built on the given base.
     *
     * @param string $baseUri The base URI the query string is appended to.
     * @return string The URI carrying the filter, sort, and pagination.
     */
    public function toUri(string $baseUri): string
    {
        $parameters = [];

        if (!$this->filter->isEmpty()) {
            $template = 'filter=%s';
            $parameters[] = sprintf($template, $this->filter->toExpression()->value());
        }

        if (!$this->sort->isEmpty()) {
            $template = 'sort=%s';
            $parameters[] = sprintf($template, $this->sort->toExpression());
        }

        $parameters[] = $this->pagination->toQueryString();
        $template = '%s?%s';

        return sprintf($template, $baseUri, implode('&', $parameters));
    }

    /**
     * Returns the sorting specification.
     *
     * @return Sort The sorting specification.
     */
    public function sorting(): Sort
    {
        return $this->sort;
    }

    /**
     * Returns the filtering specification as the root of the filter tree.
     *
     * @return Filter The filter tree root, an empty {@see Group} when no filter is present.
     */
    public function filtering(): Filter
    {
        return $this->filter;
    }

    /**
     * Creates a CursorPage from the items and the ordering-key extractor.
     *
     * @template TValue
     * @param iterable<TValue> $items The items fetched for the page size plus one.
     * @param Closure(TValue): list<mixed> $keysOf Extracts the ordering key values from an element.
     * @return CursorPage<TValue> The cursor page carrying the trimmed items and the cursors.
     * @throws PageSizeOutOfRange If the page size is less than 1.
     */
    public function cursorPage(iterable $items, Closure $keysOf): CursorPage
    {
        return CursorPage::from(items: $items, keysOf: $keysOf, criteria: $this, pagination: $this->page->toCursor());
    }

    /**
     * Creates an OffsetPage from the items and the total element count.
     *
     * @template TValue
     * @param iterable<TValue> $items The items on the current page.
     * @param int $total The total element count across every page.
     * @return OffsetPage<TValue> The offset page carrying the items, the total, and the navigation.
     * @throws TotalIsNegative If the total is less than 0.
     * @throws PageNumberOutOfRange If the page number is less than 1.
     * @throws PageSizeOutOfRange If the page size falls outside the valid range.
     */
    public function offsetPage(iterable $items, int $total): OffsetPage
    {
        return OffsetPage::from(items: $items, total: $total, criteria: $this, pagination: $this->page->toOffset());
    }

    /**
     * Returns the pagination specification.
     *
     * @return Pagination The pagination, cursor-based when a cursor is present.
     */
    public function pagination(): Pagination
    {
        return $this->pagination;
    }

    /**
     * Creates an OffsetSlice from the items, without a total element count.
     *
     * @template TValue
     * @param iterable<TValue> $items The items fetched for the page size plus one.
     * @return OffsetSlice<TValue> The offset slice carrying the trimmed items and the next-page hint.
     * @throws PageNumberOutOfRange If the page number is less than 1.
     * @throws PageSizeOutOfRange If the page size falls outside the valid range.
     */
    public function offsetSlice(iterable $items): OffsetSlice
    {
        return OffsetSlice::from(items: $items, criteria: $this, pagination: $this->page->toOffset());
    }

    /**
     * Returns a copy of the criteria with the pagination replaced.
     *
     * @param Pagination $pagination The pagination to apply.
     * @return Criteria A new criteria carrying the given pagination with the filter and sort preserved.
     */
    public function withPagination(Pagination $pagination): Criteria
    {
        return new Criteria(page: $this->page, sort: $this->sort, filter: $this->filter, pagination: $pagination);
    }
}
