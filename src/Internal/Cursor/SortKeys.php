<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Cursor;

use Closure;
use TinyBlocks\HttpQuery\Order;
use TinyBlocks\HttpQuery\Sort;

final class SortKeys
{
    private function __construct()
    {
    }

    public static function from(Sort $sort): Closure
    {
        $orders = $sort->orders();

        return static fn(array $row): array => array_map(
            static fn(Order $order): mixed => $row[$order->field()],
            $orders
        );
    }
}
