<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Clause;

use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\FilterColumnNotMapped;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\Internal\FilterClause;
use TinyBlocks\HttpQuery\LogicalOperator;

/**
 * Predicate assembled from comparisons mapped onto their columns.
 */
final readonly class Filters implements SqlClause
{
    /**
     * @param string $sql The predicate rendered from the comparisons.
     * @param array<string, mixed> $parameters The values bound to the predicate placeholders.
     */
    private function __construct(private string $sql, private array $parameters)
    {
    }

    /**
     * Assembles the conjunction predicate from the columns and a flat list of comparisons.
     *
     * <p>Each comparison is rendered by the first custom renderer that supports its operator, or by
     * the built-in rendering when none does, and the comparisons are joined with AND.</p>
     *
     * @param FilterColumns $columns The column each filterable field maps to.
     * @param list<Comparison> $comparisons The comparisons to render against the columns.
     * @param OperatorRenderer ...$renderers The custom renderers consulted before the built-in rendering.
     * @return Filters The predicate joining every comparison with AND.
     * @throws FilterColumnNotMapped When a comparison targets a field absent from the columns.
     */
    public static function from(FilterColumns $columns, array $comparisons, OperatorRenderer ...$renderers): Filters
    {
        $parameters = [];
        $fragments = [];

        foreach ($comparisons as $comparison) {
            $fragment = self::renderComparison(
                offset: count($parameters),
                columns: $columns,
                renderers: $renderers,
                comparison: $comparison
            );
            $fragments[] = $fragment->sql();
            $parameters = [...$parameters, ...$fragment->parameters()];
        }

        return new Filters(sql: implode(' AND ', $fragments), parameters: $parameters);
    }

    /**
     * Renders any filter node into a fragment, threading the placeholder offset.
     *
     * @param Filter $filter The node to render.
     * @param int $offset The number of placeholders already bound by earlier fragments.
     * @param FilterColumns $columns The column each field maps to.
     * @param array<OperatorRenderer> $renderers The custom renderers consulted before the built-in rendering.
     * @return Fragment The rendered fragment.
     */
    private static function render(Filter $filter, int $offset, FilterColumns $columns, array $renderers): Fragment
    {
        if ($filter instanceof Group) {
            return self::renderGroup(group: $filter, offset: $offset, columns: $columns, renderers: $renderers);
        }

        /** @var Comparison $filter */
        return self::renderComparison(offset: $offset, columns: $columns, renderers: $renderers, comparison: $filter);
    }

    /**
     * Assembles the predicate from the columns and the full filter tree, honoring every connective.
     *
     * <p>A group renders its non-empty children joined by its own connective and wrapped in
     * parentheses, so an OR group and a nested group survive end-to-end. An empty tree renders an
     * empty predicate.</p>
     *
     * @param Filter $filter The full filter tree to render.
     * @param FilterColumns $columns The column each filterable field maps to.
     * @param OperatorRenderer ...$renderers The custom renderers consulted before the built-in rendering.
     * @return Filters The predicate rendered from the tree.
     * @throws FilterColumnNotMapped When a comparison targets a field absent from the columns.
     */
    public static function fromTree(Filter $filter, FilterColumns $columns, OperatorRenderer ...$renderers): Filters
    {
        $fragment = self::render(filter: $filter, offset: 0, columns: $columns, renderers: $renderers);

        return new Filters(sql: $fragment->sql(), parameters: $fragment->parameters());
    }

    /**
     * @param list<string> $parts
     * @param array<string, mixed> $parameters
     */
    private static function joinParts(Group $group, array $parts, array $parameters): Fragment
    {
        if ($parts === []) {
            return Fragment::of(sql: '', parameters: []);
        }

        $connective = $group->operator() === LogicalOperator::AND ? ' AND ' : ' OR ';
        $sql = count($parts) === 1 ? $parts[0] : sprintf('(%s)', implode($connective, $parts));

        return Fragment::of(sql: $sql, parameters: $parameters);
    }

    /**
     * Renders a group, joining its non-empty children with the group connective.
     *
     * @param Group $group The group to render.
     * @param int $offset The number of placeholders already bound by earlier fragments.
     * @param FilterColumns $columns The column each field maps to.
     * @param array<OperatorRenderer> $renderers The custom renderers consulted before the built-in rendering.
     * @return Fragment The rendered group fragment, empty when no child renders.
     */
    private static function renderGroup(Group $group, int $offset, FilterColumns $columns, array $renderers): Fragment
    {
        $parameters = [];
        $parts = [];

        foreach ($group->filters() as $child) {
            $fragment = self::render(
                filter: $child,
                offset: ($offset + count($parameters)),
                columns: $columns,
                renderers: $renderers
            );

            if ($fragment->sql() === '') {
                continue;
            }

            $parts[] = $fragment->sql();
            $parameters = [...$parameters, ...$fragment->parameters()];
        }

        return self::joinParts(group: $group, parts: $parts, parameters: $parameters);
    }

    /**
     * Renders a single comparison, by the first supporting custom renderer or the built-in rendering.
     *
     * @param int $offset The number of placeholders already bound by earlier fragments.
     * @param FilterColumns $columns The column each field maps to.
     * @param array<OperatorRenderer> $renderers The custom renderers consulted before the built-in rendering.
     * @param Comparison $comparison The comparison to render.
     * @return Fragment The rendered comparison fragment.
     */
    private static function renderComparison(
        int $offset,
        FilterColumns $columns,
        array $renderers,
        Comparison $comparison
    ): Fragment {
        $column = $columns->for(field: $comparison->field());
        $renderer = array_find(
            $renderers,
            static fn(OperatorRenderer $renderer): bool => $renderer->supports(operator: $comparison->operator())
        );

        return is_null($renderer)
            ? FilterClause::from(column: $column, offset: $offset, comparison: $comparison)
            : $renderer->render(column: $column, offset: $offset, comparison: $comparison);
    }

    /**
     * Returns the rendered predicate.
     *
     * @return string The rendered predicate.
     */
    public function sql(): string
    {
        return $this->sql;
    }

    public function isEmpty(): bool
    {
        return $this->sql === '';
    }

    /**
     * Returns the values bound to the predicate placeholders.
     *
     * @return array<string, mixed> The values bound to the predicate placeholders.
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
