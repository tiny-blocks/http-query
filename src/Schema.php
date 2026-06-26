<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Exceptions\FilterFieldNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\FilterOperatorNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\FilterShapeNotSupported;
use TinyBlocks\HttpQuery\Exceptions\FilterValueNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\SortFieldNotAllowed;
use TinyBlocks\HttpQuery\Internal\AllowedFilters;
use TinyBlocks\HttpQuery\Internal\Conjunction;

/**
 * Declarative contract of the query an endpoint accepts, used to validate an incoming request.
 *
 * <p>It declares the filterable fields with their permitted operators, values, and kinds, the
 * client-sortable fields, the sort applied when the client sends none, and the page-size bounds.
 * The query parameter names follow JSON:API and are fixed: <code>filter</code>, <code>sort</code>,
 * and the <code>page</code> family. The default page size is 20 and the maximum is 100.</p>
 */
final readonly class Schema
{
    private function __construct(
        private AllowedFilters $allowed,
        private Sort $byDefault,
        private int $maxPerPage,
        private int $defaultPerPage,
        private array $sortableFields,
        private bool $allowsDisjunction
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
     * Creates a Schema with an empty contract and the default page-size bounds.
     *
     * <p>The default page size is 20 and the maximum is 100. No field is filterable or sortable, and
     * there is no default sort.</p>
     *
     * @return Schema The empty schema with a default page size of 20 and a maximum of 100.
     */
    public static function create(): Schema
    {
        return Schema::default();
    }

    /**
     * Creates a Schema with an empty contract and the default page-size bounds.
     *
     * <p>The default page size is 20 and the maximum is 100. No field is filterable or sortable, and
     * there is no default sort.</p>
     *
     * @return Schema The default schema with a default page size of 20 and a maximum of 100.
     */
    public static function default(): Schema
    {
        return new Schema(
            allowed: AllowedFilters::createFromEmpty(),
            byDefault: Sort::fromExpression(expression: ''),
            maxPerPage: 100,
            defaultPerPage: 20,
            sortableFields: [],
            allowsDisjunction: false
        );
    }

    /**
     * Returns the effective sort, the configured default when the client omits one.
     *
     * @param Sort $sort The incoming sort parsed from the request.
     * @return Sort The effective sort applied to the store.
     * @throws SortFieldNotAllowed If an order targets a field that was never declared sortable.
     */
    public function sortFor(Sort $sort): Sort
    {
        if ($sort->isEmpty()) {
            return $this->byDefault;
        }

        foreach ($sort->orders() as $order) {
            if (!in_array($order->field(), $this->sortableFields, true)) {
                throw SortFieldNotAllowed::from(field: $order->field());
            }
        }

        return $sort;
    }

    /**
     * Returns a copy of the Schema declaring the fields the client may sort by.
     *
     * @param list<string> $fields The fields the client may sort by.
     * @return Schema A copy carrying the client-sortable fields.
     */
    public function sortable(array $fields): Schema
    {
        return new Schema(
            allowed: $this->allowed,
            byDefault: $this->byDefault,
            maxPerPage: $this->maxPerPage,
            defaultPerPage: $this->defaultPerPage,
            sortableFields: $fields,
            allowsDisjunction: $this->allowsDisjunction
        );
    }

    /**
     * Returns a copy of the Schema allowing the field under the operators, values, and kind.
     *
     * @param string $field The field the endpoint exposes for filtering.
     * @param list<Operator> $operators The operators permitted for the field.
     * @param ValueKind|null $valueKind The kind every value must match, or null to skip the kind check.
     * @param list<string>|null $allowedValues The permitted values, or null to permit any value.
     * @return Schema A copy carrying the original contract plus the allowed field.
     */
    public function filterable(
        string $field,
        array $operators,
        ?ValueKind $valueKind = null,
        ?array $allowedValues = null
    ): Schema {
        return new Schema(
            allowed: $this->allowed->with(
                kind: $valueKind,
                field: $field,
                values: $allowedValues,
                operators: $operators
            ),
            byDefault: $this->byDefault,
            maxPerPage: $this->maxPerPage,
            defaultPerPage: $this->defaultPerPage,
            sortableFields: $this->sortableFields,
            allowsDisjunction: $this->allowsDisjunction
        );
    }

    /**
     * Returns a copy of the Schema with the maximum page size replaced.
     *
     * @param int $maxPerPage The maximum allowed page size.
     * @return Schema A copy of the schema carrying the new maximum page size.
     * @throws PageSizeOutOfRange If the maximum is below 1 or below the default page size.
     */
    public function maxPerPage(int $maxPerPage): Schema
    {
        return new Schema(
            allowed: $this->allowed,
            byDefault: $this->byDefault,
            maxPerPage: $maxPerPage,
            defaultPerPage: $this->defaultPerPage,
            sortableFields: $this->sortableFields,
            allowsDisjunction: $this->allowsDisjunction
        );
    }

    /**
     * Returns a copy of the Schema with the sort applied when the client sends none.
     *
     * @param Sort $sort The sort applied when the client omits one.
     * @return Schema A copy carrying the default sort.
     */
    public function defaultSort(Sort $sort): Schema
    {
        return new Schema(
            allowed: $this->allowed,
            byDefault: $sort,
            maxPerPage: $this->maxPerPage,
            defaultPerPage: $this->defaultPerPage,
            sortableFields: $this->sortableFields,
            allowsDisjunction: $this->allowsDisjunction
        );
    }

    /**
     * Returns the requested page size, the default when none is requested, bounded by the maximum.
     *
     * @param int|null $requested The requested page size, or null when the query omits one.
     * @return int The effective page size within the maximum.
     * @throws PageSizeOutOfRange If the requested size exceeds the maximum.
     */
    public function pageSizeFor(?int $requested): int
    {
        $size = is_null($requested) ? $this->defaultPerPage : $requested;

        if ($size > $this->maxPerPage) {
            throw PageSizeOutOfRange::aboveMaximum(maximum: $this->maxPerPage, perPage: $size);
        }

        return $size;
    }

    /**
     * Returns the validated comparisons read from the filter.
     *
     * <p>By default the filter must be a single comparison or an AND group of comparisons, and any
     * other shape is rejected. When the schema allows disjunction, every comparison leaf of the
     * filter tree is validated regardless of the connective, and the consumer reads the tree from
     * <code>Criteria::filter()</code> to render it.</p>
     *
     * @param Filter $filter The incoming filter parsed from the request.
     * @param string $expression The raw filter query string, rendered in shape-violation messages.
     * @return list<Comparison> The validated comparison leaves in filter order.
     * @throws FilterShapeNotSupported If disjunction is not allowed and the filter is not an AND group.
     * @throws FilterFieldNotAllowed If a comparison targets a field that was never allowed.
     * @throws FilterOperatorNotAllowed If a comparison uses an operator not allowed for its field.
     * @throws FilterValueNotAllowed If a compared value falls outside the permitted set or kind.
     */
    public function comparisonsFor(Filter $filter, string $expression): array
    {
        /** @var list<Comparison> $comparisons */
        $comparisons = $this->allowsDisjunction
            ? Conjunction::leaves(filter: $filter)
            : Conjunction::from(filter: $filter, expression: $expression);

        return array_map(
            fn(Comparison $comparison): Comparison => $this->allowed->permit(comparison: $comparison),
            $comparisons
        );
    }

    /**
     * Returns a copy of the Schema with the default page size replaced.
     *
     * @param int $defaultPerPage The page size applied when the query omits one.
     * @return Schema A copy of the schema carrying the new default page size.
     * @throws PageSizeOutOfRange If the default is below 1 or above the maximum page size.
     */
    public function defaultPerPage(int $defaultPerPage): Schema
    {
        return new Schema(
            allowed: $this->allowed,
            byDefault: $this->byDefault,
            maxPerPage: $this->maxPerPage,
            defaultPerPage: $defaultPerPage,
            sortableFields: $this->sortableFields,
            allowsDisjunction: $this->allowsDisjunction
        );
    }

    /**
     * Returns a copy of the Schema accepting OR groups and nested groups in the filter.
     *
     * @return Schema A copy that no longer rejects a disjunction in the filter shape.
     */
    public function allowDisjunction(): Schema
    {
        return new Schema(
            allowed: $this->allowed,
            byDefault: $this->byDefault,
            maxPerPage: $this->maxPerPage,
            defaultPerPage: $this->defaultPerPage,
            sortableFields: $this->sortableFields,
            allowsDisjunction: true
        );
    }
}
