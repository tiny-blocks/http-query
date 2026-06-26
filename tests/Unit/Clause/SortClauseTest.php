<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Clause;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Clause\FilterColumns;
use TinyBlocks\HttpQuery\Clause\SortClause;
use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Order;

final class SortClauseTest extends TestCase
{
    public function testFromWhenNoOrdersThenRendersAnEmptyOrdering(): void
    {
        /** @Given no orders */
        $orders = [];

        /** @When the ordering is rendered */
        $ordering = SortClause::from(
            orders: $orders,
            columns: FilterColumns::create()->plain(field: 'name', column: 'pme.name')
        );

        /** @Then the ordering is empty */
        self::assertTrue($ordering->isEmpty());
        self::assertSame('', $ordering->sql());
        self::assertSame([], $ordering->parameters());
    }

    public function testFromWhenAscendingThenRendersTheColumnAscending(): void
    {
        /** @Given an order ascending by a mapped field */
        $order = Order::from(field: 'name', direction: Direction::ASCENDING);

        /** @When the ordering is rendered */
        $ordering = SortClause::from(
            orders: [$order],
            columns: FilterColumns::create()->plain(field: 'name', column: 'pme.name')
        );

        /** @Then the column is rendered ascending */
        self::assertSame('pme.name ASC', $ordering->sql());
    }

    public function testFromWhenDescendingThenRendersTheColumnDescending(): void
    {
        /** @Given an order descending by a mapped field */
        $order = Order::from(field: 'created_at', direction: Direction::DESCENDING);

        /** @When the ordering is rendered */
        $ordering = SortClause::from(
            orders: [$order],
            columns: FilterColumns::create()->plain(field: 'created_at', column: 'pay.created_at')
        );

        /** @Then the column is rendered descending */
        self::assertSame('pay.created_at DESC', $ordering->sql());
    }

    public function testFromWhenMultipleOrdersThenJoinsThemWithACommaInOrder(): void
    {
        /** @Given an order ascending by one field */
        $priority = Order::from(field: 'priority', direction: Direction::ASCENDING);

        /** @And an order descending by another field */
        $country = Order::from(field: 'country', direction: Direction::DESCENDING);

        /** @When the ordering is rendered */
        $ordering = SortClause::from(
            orders: [$priority, $country],
            columns: FilterColumns::create()
                ->plain(field: 'priority', column: 'prr.priority')
                ->plain(field: 'country', column: 'prr.country')
        );

        /** @Then both columns are joined with a comma in declared order */
        self::assertSame('prr.priority ASC, prr.country DESC', $ordering->sql());
    }
}
