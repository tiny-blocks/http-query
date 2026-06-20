<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Clause;

use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Order;

/**
 * Ordering rendered from a set of orders mapped onto their columns.
 */
final readonly class SortClause implements SqlClause
{
    private function __construct(private string $sql)
    {
    }

    /**
     * Renders the ordering from the orders and the columns.
     *
     * @param list<Order> $orders The orders to render.
     * @param FilterColumns $columns The column each ordered field maps to.
     * @return SortClause The comma-separated ordering, each column paired with its direction.
     */
    public static function from(array $orders, FilterColumns $columns): SortClause
    {
        $parts = array_map(
            static fn(Order $order): string => sprintf(
                '%s %s',
                $columns->for(field: $order->field())->column(),
                $order->direction() === Direction::DESCENDING ? 'DESC' : 'ASC'
            ),
            $orders
        );

        return new SortClause(sql: implode(', ', $parts));
    }

    /**
     * Returns the comma-separated ordering, each column paired with its direction.
     *
     * @return string The comma-separated ordering.
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
     * Returns the parameters bound to the ordering, always empty.
     *
     * @return array<string, mixed> The empty parameter set.
     */
    public function parameters(): array
    {
        return [];
    }
}
