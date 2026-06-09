<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Rsql;

use TinyBlocks\Collection\Collection;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Group;

final class Renderer
{
    private function __construct()
    {
    }

    public static function from(Filter $filter): string
    {
        return Renderer::expressionOf(filter: $filter)->value();
    }

    private static function expressionOf(Filter $filter): Expression
    {
        return match (true) {
            $filter instanceof Group      => Renderer::grouped(group: $filter),
            $filter instanceof Comparison => Renderer::atomic(comparison: $filter)
        };
    }

    private static function atomic(Comparison $comparison): Expression
    {
        /** @var Collection<string> $values */
        $values = Collection::createFrom(elements: $comparison->values());

        $arguments = $values
            ->map(transformations: static function (string $value): string {
                if ($value !== '' && preg_match('/[\s"\'();,=!~<>]/', $value) !== 1) {
                    return $value;
                }

                $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
                $template = '"%s"';

                return sprintf($template, $escaped);
            })
            ->joinToString(separator: ',');

        $template = $comparison->operator()->isMultiValued() ? '%s%s(%s)' : '%s%s%s';

        return Expression::atomic(
            value: sprintf($template, $comparison->field(), $comparison->operator()->value, $arguments)
        );
    }

    private static function grouped(Group $group): Expression
    {
        $operator = $group->operator();

        /** @var Collection<Filter> $filters */
        $filters = Collection::createFrom(elements: $group->filters());

        $value = $filters
            ->map(transformations: static fn(Filter $filter): string => Renderer::expressionOf(filter: $filter)
                ->nestedWithin(parent: $operator))
            ->joinToString(separator: $operator->value);

        return Expression::grouped(value: $value, connective: $operator);
    }
}
