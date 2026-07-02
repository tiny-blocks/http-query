<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Clause\FilterColumn;
use TinyBlocks\HttpQuery\Clause\Fragment;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Operator;

/**
 * Built-in rendering of a single comparison mapped onto its column.
 */
final readonly class FilterClause
{
    /**
     * Renders the built-in fragment from the column, the placeholder offset, and the comparison.
     *
     * @param FilterColumn $column The column the comparison field maps to.
     * @param int $offset The number of placeholders already bound by earlier fragments.
     * @param Comparison $comparison The comparison to render against the column.
     * @return Fragment The fragment rendered for the comparison.
     */
    public static function from(FilterColumn $column, int $offset, Comparison $comparison): Fragment
    {
        $values = array_map($column->normalize(...), $comparison->values());
        $names = array_map(
            static fn(int $index): string => sprintf('filter_%d', ($offset + $index)),
            array_keys($values)
        );
        $parameters = array_combine($names, $values);
        $placeholders = array_map(
            static fn(string $name): string => sprintf($column->binding(), sprintf(':%s', $name)),
            $names
        );

        $sql = match ($comparison->operator()) {
            Operator::IN                    =>
            sprintf('%s IN (%s)', $column->column(), implode(', ', $placeholders)),
            Operator::NOT_IN                =>
            sprintf('%s NOT IN (%s)', $column->column(), implode(', ', $placeholders)),
            Operator::EQUAL                 =>
            sprintf('%s = %s', $column->column(), $placeholders[0]),
            Operator::NOT_EQUAL             =>
            sprintf('%s <> %s', $column->column(), $placeholders[0]),
            Operator::LESS_THAN             =>
            sprintf('%s < %s', $column->column(), $placeholders[0]),
            Operator::GREATER_THAN          =>
            sprintf('%s > %s', $column->column(), $placeholders[0]),
            Operator::LESS_THAN_OR_EQUAL    =>
            sprintf('%s <= %s', $column->column(), $placeholders[0]),
            Operator::GREATER_THAN_OR_EQUAL =>
            sprintf('%s >= %s', $column->column(), $placeholders[0])
        };

        return Fragment::of(sql: $sql, parameters: $parameters);
    }
}
