<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Exceptions\SortExpressionIsInvalid;
use TinyBlocks\HttpQuery\Sort;

final class SortTest extends TestCase
{
    public function testFromExpressionWhenEmptyThenIsEmpty(): void
    {
        /** @Given an empty sort expression */
        $expression = '';

        /** @When parsing the sort expression */
        $sort = Sort::fromExpression(expression: $expression);

        /** @Then the sort carries no order */
        self::assertTrue($sort->isEmpty());

        /** @And the orders are an empty list */
        self::assertSame([], $sort->orders());
    }

    public function testFromExpressionWhenWhitespaceOnlyThenIsEmpty(): void
    {
        /** @Given a whitespace-only sort expression */
        $expression = '   ';

        /** @When parsing the sort expression */
        $sort = Sort::fromExpression(expression: $expression);

        /** @Then the sort carries no order */
        self::assertTrue($sort->isEmpty());

        /** @And the orders are an empty list */
        self::assertSame([], $sort->orders());
    }

    public function testFromExpressionWhenSingleFieldThenSingleAscendingOrder(): void
    {
        /** @Given a single ascending field */
        $expression = 'name';

        /** @When parsing the sort expression */
        $sort = Sort::fromExpression(expression: $expression);

        /** @Then the sort is not empty */
        self::assertFalse($sort->isEmpty());

        /** @And the orders carry exactly one order */
        self::assertCount(1, $sort->orders());

        /** @And the single order sorts by the given field */
        self::assertSame('name', $sort->orders()[0]->field());

        /** @And the single order applies ascending direction */
        self::assertSame(Direction::ASCENDING, $sort->orders()[0]->direction());
    }

    #[DataProvider('invalidExpressionsProvider')]
    public function testFromExpressionWhenMalformedThenThrowsSortExpressionIsInvalid(string $expression): void
    {
        /** @Given a malformed sort expression */

        /** @Then an exception indicating the sort expression is invalid is raised */
        $this->expectException(SortExpressionIsInvalid::class);
        $this->expectExceptionMessage('Sort expression');

        /** @When parsing the sort expression */
        Sort::fromExpression(expression: $expression);
    }

    public function testToExpressionWhenDescendingAndAscendingFieldsThenRendersTheSortExpression(): void
    {
        /** @Given a sort parsed from a descending field followed by an ascending field */
        $sort = Sort::fromExpression(expression: '-created_at,id');

        /** @When rendering it back to a sort expression */
        $expression = $sort->toExpression();

        /** @Then the descending field carries a leading minus and the ascending field does not */
        self::assertSame('-created_at,id', $expression);
    }

    public function testFromExpressionWhenLeadingMinusAndPlainFieldThenPreservesOrderAndDirection(): void
    {
        /** @Given a descending field followed by an ascending field */
        $expression = '-created_at,id';

        /** @When parsing the sort expression */
        $sort = Sort::fromExpression(expression: $expression);

        /** @Then the sort is not empty */
        self::assertFalse($sort->isEmpty());

        /** @And the orders carry exactly two orders */
        self::assertCount(2, $sort->orders());

        /** @And the first order sorts by the descending field */
        self::assertSame('created_at', $sort->orders()[0]->field());

        /** @And the first order applies descending direction */
        self::assertSame(Direction::DESCENDING, $sort->orders()[0]->direction());

        /** @And the second order sorts by the ascending field */
        self::assertSame('id', $sort->orders()[1]->field());

        /** @And the second order applies ascending direction */
        self::assertSame(Direction::ASCENDING, $sort->orders()[1]->direction());
    }

    public static function invalidExpressionsProvider(): array
    {
        return [
            'Trailing comma'   => ['expression' => 'id,'],
            'Just minus sign'  => ['expression' => '-'],
            'Field with space' => ['expression' => 'a b']
        ];
    }
}
