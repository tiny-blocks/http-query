<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\Http\Server\Decoded\QueryParameters;
use TinyBlocks\HttpQuery\Exceptions\FilterExpressionIsInvalid;
use TinyBlocks\HttpQuery\Exceptions\OffsetOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageNumberOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\SortExpressionIsInvalid;
use TinyBlocks\HttpQuery\Internal\PaginationResolver;
use TinyBlocks\HttpQuery\Internal\Rsql\FilterParser;

/**
 * Umbrella specification of a collection query, pairing filtering, sorting, and pagination.
 *
 * <p>It parses from the request query string and serializes back to a URI, so the navigation links
 * built from it preserve the filter and the sort.</p>
 */
final readonly class Criteria
{
    private function __construct(
        private Sort $sort,
        private Filter $filter,
        private Schema $schema,
        private Paging $pagination
    ) {
    }

    /**
     * Creates a Criteria from the request query parameters and an optional schema.
     *
     * <p>When the schema is omitted, the canonical default key names and bounds apply. The
     * pagination is a {@see CursorPagination} when the cursor key is present, otherwise an
     * offset-based {@see Pagination}.</p>
     *
     * @param QueryParameters $query The decoded request query parameters.
     * @param Schema|null $schema The schema mapping the query keys and bounds, or null for the default.
     * @return Criteria The criteria carrying the parsed filter, sort, and pagination.
     * @throws FilterExpressionIsInvalid If the filter expression cannot be parsed.
     * @throws SortExpressionIsInvalid If the sort expression cannot be parsed.
     * @throws PageNumberOutOfRange If the page number is less than 1.
     * @throws PageSizeOutOfRange If the page size falls outside the valid range.
     * @throws OffsetOutOfRange If the offset is less than 0.
     */
    public static function fromQuery(QueryParameters $query, ?Schema $schema = null): Criteria
    {
        $schema = $schema ?? Schema::default();
        $expression = $query->get(key: $schema->filterKey())->toString();
        $filter = $expression === '' ? Group::none() : FilterParser::from(input: $expression)->parse();

        $sort = Sort::fromExpression(expression: $query->get(key: $schema->sortKey())->toString());
        $pagination = PaginationResolver::from(query: $query, schema: $schema)->resolve();

        return new Criteria(sort: $sort, filter: $filter, schema: $schema, pagination: $pagination);
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
        $template = '%s=%s';

        if (!$this->filter->isEmpty()) {
            $parameters[] = sprintf($template, $this->schema->filterKey(), $this->filter->toExpression()->value());
        }

        if (!$this->sort->isEmpty()) {
            $parameters[] = sprintf($template, $this->schema->sortKey(), $this->sort->toExpression());
        }

        $parameters[] = $this->pagination->toQueryString(schema: $this->schema);
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
     * Returns the pagination specification.
     *
     * @return Paging The pagination, cursor-based when a cursor is present.
     */
    public function pagination(): Paging
    {
        return $this->pagination;
    }

    /**
     * Returns a copy of the criteria with the pagination replaced.
     *
     * @param Paging $pagination The pagination to apply.
     * @return Criteria A new criteria carrying the given pagination with the filter and sort preserved.
     */
    public function withPagination(Paging $pagination): Criteria
    {
        return new Criteria(sort: $this->sort, filter: $this->filter, schema: $this->schema, pagination: $pagination);
    }
}
