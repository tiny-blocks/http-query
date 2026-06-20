<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Clause;

use TinyBlocks\HttpQuery\Cursor\Keyset;

/**
 * Seek predicate assembled from a keyset cursor mapped onto its columns.
 */
final readonly class SeekClause implements SqlClause
{
    /**
     * @param string $sql The row-value predicate selecting the rows beyond the cursor.
     * @param array<string, mixed> $parameters The cursor key values bound to the predicate placeholders.
     */
    private function __construct(private string $sql, private array $parameters)
    {
    }

    /**
     * Assembles the seek predicate from the keyset cursor and the columns.
     *
     * <p>The predicate is empty on the first page, when the cursor carries no incoming key values.
     * From the second page on, it renders the row-value comparison selecting the rows ordered
     * beyond the cursor, every earlier key tied with equality and the next key sought with the
     * direction operator.</p>
     *
     * @param Keyset $keyset The cursor view carrying the effective orders and the incoming cursor.
     * @param FilterColumns $columns The column each ordered field maps to.
     * @return SeekClause The row-value predicate selecting the rows beyond the cursor.
     */
    public static function from(Keyset $keyset, FilterColumns $columns): SeekClause
    {
        $orders = $keyset->orders();
        $cursor = $keyset->cursor();

        if (is_null($cursor[$orders[0]->field()])) {
            return new SeekClause(sql: '', parameters: []);
        }

        $parameters = [];
        $clauses = [];
        $equalities = [];

        foreach ($orders as $order) {
            $field = $order->field();
            $column = $columns->for(field: $field);
            $name = sprintf('seek_%s', $field);
            $bind = sprintf($column->binding(), sprintf(':%s', $name));

            $parameters[$name] = $cursor[$field];
            $predicate = sprintf('%s %s %s', $column->column(), $order->direction()->seekOperator(), $bind);
            $clauses[] = sprintf('(%s)', implode(' AND ', [...$equalities, $predicate]));
            $equalities[] = sprintf('%s = %s', $column->column(), $bind);
        }

        return new SeekClause(sql: sprintf('(%s)', implode(' OR ', $clauses)), parameters: $parameters);
    }

    /**
     * Returns the row-value predicate selecting the rows beyond the cursor.
     *
     * @return string The row-value predicate selecting the rows beyond the cursor.
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
     * Returns the cursor key values bound to the predicate placeholders.
     *
     * @return array<string, mixed> The cursor key values bound to the predicate placeholders.
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
