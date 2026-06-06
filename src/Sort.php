<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\Collection\Collection;
use TinyBlocks\HttpQuery\Exceptions\SortExpressionIsInvalid;

/**
 * Ordered set of sort fields parsed from a JSON:API-style sort expression.
 *
 * <p>Fields are comma-separated and a leading minus marks descending order, for example
 * <code>-created_at,id</code>. The request order is preserved.</p>
 */
final readonly class Sort
{
    private function __construct(private array $orders)
    {
    }

    /**
     * Creates a Sort from a comma-separated sort expression.
     *
     * <p>An empty expression yields an empty Sort. A leading minus on a field marks descending
     * order, every other field is ascending.</p>
     *
     * @param string $expression The comma-separated sort expression.
     * @return Sort The parsed ordering with the request order preserved.
     * @throws SortExpressionIsInvalid If a field is empty or carries reserved characters.
     */
    public static function fromExpression(string $expression): Sort
    {
        $trimmed = trim($expression);

        if ($trimmed === '') {
            return new Sort(orders: []);
        }

        $orders = array_map(static function (string $token) use ($expression): Order {
            $descending = str_starts_with($token, '-');
            $field = $descending ? substr($token, 1) : $token;

            if (preg_match('/^\w[\w.-]*$/', $field) !== 1) {
                throw SortExpressionIsInvalid::from(expression: $expression);
            }

            $direction = $descending ? Direction::DESCENDING : Direction::ASCENDING;

            return Order::from(field: $field, direction: $direction);
        }, explode(',', $trimmed));

        return new Sort(orders: $orders);
    }

    /**
     * Returns the orders in the request order.
     *
     * @return list<Order> The orders in the request order.
     */
    public function orders(): array
    {
        return $this->orders;
    }

    /**
     * Tells whether no sort field is present.
     *
     * @return bool True when the Sort carries no order.
     */
    public function isEmpty(): bool
    {
        return $this->orders === [];
    }

    /**
     * Returns the Sort as a comma-separated sort expression.
     *
     * @return string The sort expression with a leading minus marking each descending field.
     */
    public function toExpression(): string
    {
        /** @var Collection<Order> $orders */
        $orders = Collection::createFrom(elements: $this->orders);

        return $orders
            ->map(transformations: static fn(Order $order): string => $order->toExpression())
            ->joinToString(separator: ',');
    }
}
