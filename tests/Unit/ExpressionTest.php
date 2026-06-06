<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Expression;
use TinyBlocks\HttpQuery\LogicalOperator;

final class ExpressionTest extends TestCase
{
    public function testValueWhenAtomicGivenThenReturnsRenderedLeaf(): void
    {
        /** @Given an atomic expression rendered from a leaf */
        $expression = Expression::atomic(value: 'status==paid');

        /** @When reading the RSQL expression */
        $actual = $expression->value();

        /** @Then it returns the rendered leaf */
        self::assertSame('status==paid', $actual);
    }

    public function testValueWhenGroupedGivenThenReturnsJoinedChildren(): void
    {
        /** @Given a grouped expression rendered from children joined by a connective */
        $expression = Expression::grouped(value: 'a==1;b==2', connective: LogicalOperator::AND);

        /** @When reading the RSQL expression */
        $actual = $expression->value();

        /** @Then it returns the joined children */
        self::assertSame('a==1;b==2', $actual);
    }

    #[DataProvider('nestingCases')]
    public function testNestedWithinWhenNestedThenParenthesizesByPrecedence(
        Expression $expression,
        LogicalOperator $parent,
        string $expected
    ): void {
        /** @Given a rendered expression and the connective it is nested within */

        /** @When rendering it nested within the parent connective */
        $actual = $expression->nestedWithin(parent: $parent);

        /** @Then it is parenthesized only when it binds looser than the parent */
        self::assertSame($expected, $actual);
    }

    public static function nestingCases(): array
    {
        return [
            'OR grouped within AND' => [
                'expression' => Expression::grouped(value: 'a==1,b==2', connective: LogicalOperator::OR),
                'parent'     => LogicalOperator::AND,
                'expected'   => '(a==1,b==2)'
            ],
            'AND grouped within OR' => [
                'expression' => Expression::grouped(value: 'a==1;b==2', connective: LogicalOperator::AND),
                'parent'     => LogicalOperator::OR,
                'expected'   => 'a==1;b==2'
            ],
            'OR grouped within OR'  => [
                'expression' => Expression::grouped(value: 'a==1,b==2', connective: LogicalOperator::OR),
                'parent'     => LogicalOperator::OR,
                'expected'   => 'a==1,b==2'
            ],
            'atomic within AND'     => [
                'expression' => Expression::atomic(value: 'a==1'),
                'parent'     => LogicalOperator::AND,
                'expected'   => 'a==1'
            ]
        ];
    }
}
