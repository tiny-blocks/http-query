<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Single sort field paired with the direction applied to it.
 */
final readonly class Order
{
    private function __construct(private string $field, private Direction $direction)
    {
    }

    /**
     * Creates an Order from a field and its direction.
     *
     * @param string $field The field to sort by.
     * @param Direction $direction The direction applied to the field.
     * @return Order The composed order.
     */
    public static function from(string $field, Direction $direction): Order
    {
        return new Order(field: $field, direction: $direction);
    }

    /**
     * Returns the field to sort by.
     *
     * @return string The field to sort by.
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * Returns the direction applied to the field.
     *
     * @return Direction The direction applied to the field.
     */
    public function direction(): Direction
    {
        return $this->direction;
    }

    /**
     * Returns the Order as its sort token.
     *
     * @return string The field with a leading minus when the direction is descending.
     */
    public function toExpression(): string
    {
        $prefix = $this->direction->prefix();
        $template = '%s%s';

        return sprintf($template, $prefix, $this->field);
    }
}
