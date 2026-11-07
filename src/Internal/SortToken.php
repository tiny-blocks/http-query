<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Exceptions\SortExpressionIsInvalid;
use TinyBlocks\HttpQuery\Order;

final readonly class SortToken
{
    private function __construct(private string $field, private Direction $direction)
    {
    }

    public static function from(string $token, string $expression): SortToken
    {
        $descending = str_starts_with($token, '-');
        $field = $descending ? substr($token, 1) : $token;
        $direction = $descending ? Direction::DESCENDING : Direction::ASCENDING;

        if (preg_match('/^\w[\w.-]*$/', $field) !== 1) {
            throw SortExpressionIsInvalid::from(expression: $expression);
        }

        return new SortToken(field: $field, direction: $direction);
    }

    public function toOrder(): Order
    {
        return Order::from(field: $this->field, direction: $this->direction);
    }
}
