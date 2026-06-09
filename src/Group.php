<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Composite of the filter tree joining child filters under a single logical connective.
 */
final readonly class Group implements Filter
{
    private function __construct(private array $filters, private LogicalOperator $operator)
    {
    }

    /**
     * Creates a Group from its child filters and the logical connective joining them.
     *
     * @param list<Filter> $filters The child filters joined by the connective.
     * @param LogicalOperator $operator The logical connective joining the children.
     * @return Group The composed group.
     */
    public static function of(array $filters, LogicalOperator $operator): Group
    {
        return new Group(filters: $filters, operator: $operator);
    }

    /**
     * Creates an empty Group that carries no child filters.
     *
     * @return Group The empty group standing for the absence of a filter.
     */
    public static function none(): Group
    {
        return new Group(filters: [], operator: LogicalOperator::AND);
    }

    /**
     * Returns the child filters joined by the connective.
     *
     * @return list<Filter> The child filters.
     */
    public function filters(): array
    {
        return $this->filters;
    }

    public function isEmpty(): bool
    {
        return $this->filters === [];
    }

    /**
     * Returns the logical connective joining the child filters.
     *
     * @return LogicalOperator The logical connective.
     */
    public function operator(): LogicalOperator
    {
        return $this->operator;
    }
}
