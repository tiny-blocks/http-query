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
    private const array TEMPLATES = [
        Operator::IN->value                    => '%s IN (%s)',
        Operator::EQUAL->value                 => '%s = %s',
        Operator::NOT_IN->value                => '%s NOT IN (%s)',
        Operator::LESS_THAN->value             => '%s < %s',
        Operator::NOT_EQUAL->value             => '%s <> %s',
        Operator::STARTS_WITH->value           => '%s LIKE %s ESCAPE \'!\'',
        Operator::GREATER_THAN->value          => '%s > %s',
        Operator::LESS_THAN_OR_EQUAL->value    => '%s <= %s',
        Operator::GREATER_THAN_OR_EQUAL->value => '%s >= %s'
    ];

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
        $operator = $comparison->operator();

        $compared = $comparison->hasOperator(operator: Operator::STARTS_WITH)
            ? array_map(AnchoredPrefix::from(...), $comparison->values())
            : $comparison->values();

        $values = array_map($column->normalize(...), $compared);
        $names = array_map(
            static fn(int $index): string => sprintf('filter_%d', ($offset + $index)),
            array_keys($values)
        );
        $parameters = array_combine($names, $values);
        $placeholders = array_map(
            static fn(string $name): string => sprintf($column->binding(), sprintf(':%s', $name)),
            $names
        );

        $argument = $operator->isMultiValued() ? implode(', ', $placeholders) : $placeholders[0];
        $sql = sprintf(self::TEMPLATES[$operator->value], $column->column(), $argument);

        return Fragment::of(sql: $sql, parameters: $parameters);
    }
}
