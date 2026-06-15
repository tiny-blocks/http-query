<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\LogicalOperator;

final class LogicalOperatorTest extends TestCase
{
    #[DataProvider('logicalOperatorCases')]
    public function testFromWhenTokenGivenThenResolvesToCase(string $token, LogicalOperator $operator): void
    {
        /** @Given a canonical token and the connective it maps to */

        /** @When resolving the connective from the token */
        $actual = LogicalOperator::from($token);

        /** @Then the resolved connective is the expected case */
        self::assertSame($operator, $actual);
    }

    #[DataProvider('logicalOperatorCases')]
    public function testValueWhenCaseGivenThenExposesCanonicalToken(string $token, LogicalOperator $operator): void
    {
        /** @Given a logical connective and its canonical token */

        /** @When reading the backing value */
        $actual = $operator->value;

        /** @Then the value equals the canonical token */
        self::assertSame($token, $actual);
    }

    #[DataProvider('precedenceCases')]
    public function testBindsTighterThanWhenComparedToOtherThenReflectsPrecedence(
        LogicalOperator $other,
        bool $expected,
        LogicalOperator $operator
    ): void {
        /** @Given a connective and another connective to compare against */

        /** @When asking whether the connective binds tighter than the other */
        $actual = $operator->bindsTighterThan(other: $other);

        /** @Then the answer reflects that AND binds tighter than OR */
        self::assertSame($expected, $actual);
    }

    public static function precedenceCases(): array
    {
        return [
            'AND over OR' => [
                'operator' => LogicalOperator::AND,
                'other'    => LogicalOperator::OR,
                'expected' => true
            ],
            'OR over AND' => [
                'operator' => LogicalOperator::OR,
                'other'    => LogicalOperator::AND,
                'expected' => false
            ],
            'AND over AND' => [
                'operator' => LogicalOperator::AND,
                'other'    => LogicalOperator::AND,
                'expected' => false
            ],
            'OR over OR' => [
                'operator' => LogicalOperator::OR,
                'other'    => LogicalOperator::OR,
                'expected' => false
            ]
        ];
    }

    public static function logicalOperatorCases(): array
    {
        return [
            'OR'  => ['operator' => LogicalOperator::OR, 'token' => ','],
            'AND' => ['operator' => LogicalOperator::AND, 'token' => ';']
        ];
    }
}
