<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\FilterShapeNotSupported;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\LogicalOperator;

final class Conjunction
{
    private function __construct()
    {
    }

    public static function from(Filter $filter, string $expression): array
    {
        if ($filter instanceof Group) {
            return Conjunction::flatten(group: $filter, expression: $expression);
        }

        return [$filter];
    }

    private static function leaf(Filter $filter, string $expression): Comparison
    {
        if ($filter instanceof Comparison) {
            return $filter;
        }

        throw FilterShapeNotSupported::from(expression: $expression);
    }

    public static function leaves(Filter $filter): array
    {
        if ($filter instanceof Group) {
            return array_merge(
                [],
                ...array_map(static fn(Filter $child): array => Conjunction::leaves(filter: $child), $filter->filters())
            );
        }

        return [$filter];
    }

    private static function flatten(Group $group, string $expression): array
    {
        if ($group->operator() !== LogicalOperator::AND) {
            throw FilterShapeNotSupported::from(expression: $expression);
        }

        return array_map(
            static fn(Filter $filter): Comparison => Conjunction::leaf(filter: $filter, expression: $expression),
            $group->filters()
        );
    }
}
