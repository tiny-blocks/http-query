<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Order;

final class OrderTest extends TestCase
{
    public function testToExpressionWhenAscendingThenRendersTheFieldWithoutPrefix(): void
    {
        /** @Given an order sorting a field in ascending direction */
        $order = Order::from(field: 'name', direction: Direction::ASCENDING);

        /** @When rendering it as a sort token */
        $expression = $order->toExpression();

        /** @Then it renders the field without a leading minus */
        self::assertSame('name', $expression);
    }

    public function testToExpressionWhenDescendingThenRendersTheFieldWithALeadingMinus(): void
    {
        /** @Given an order sorting a field in descending direction */
        $order = Order::from(field: 'created_at', direction: Direction::DESCENDING);

        /** @When rendering it as a sort token */
        $expression = $order->toExpression();

        /** @Then it renders the field with a leading minus */
        self::assertSame('-created_at', $expression);
    }
}
