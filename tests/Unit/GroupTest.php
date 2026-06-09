<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\LogicalOperator;
use TinyBlocks\HttpQuery\Operator;

final class GroupTest extends TestCase
{
    public function testFiltersThenReturnsTheChildFilters(): void
    {
        /** @Given a comparison on one field */
        $first = Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL);

        /** @And a comparison on another field */
        $second = Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL);

        /** @And an AND group joining both comparisons */
        $group = Group::of(filters: [$first, $second], operator: LogicalOperator::AND);

        /** @When reading the child filters */
        $filters = $group->filters();

        /** @Then it returns the child filters in order */
        self::assertSame([$first, $second], $filters);
    }

    public function testIsEmptyWhenGroupHasNoChildThenIsEmpty(): void
    {
        /** @Given an empty group */
        $group = Group::none();

        /** @When checking whether it is empty */
        $isEmpty = $group->isEmpty();

        /** @Then it reports itself as empty */
        self::assertTrue($isEmpty);
    }

    public function testIsEmptyWhenGroupHasChildThenIsNotEmpty(): void
    {
        /** @Given a group joining a single comparison */
        $group = Group::of(filters: [
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND);

        /** @When checking whether it is empty */
        $isEmpty = $group->isEmpty();

        /** @Then it reports itself as not empty */
        self::assertFalse($isEmpty);
    }

    public function testOperatorThenReturnsTheLogicalConnective(): void
    {
        /** @Given an AND group joining two comparisons */
        $group = Group::of(filters: [
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
            Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND);

        /** @When reading the logical connective */
        $operator = $group->operator();

        /** @Then it returns the connective joining the children */
        self::assertSame(LogicalOperator::AND, $operator);
    }
}
