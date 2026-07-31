<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Operator;

final class OperatorTest extends TestCase
{
    #[DataProvider('operatorCases')]
    public function testFromWhenTokenGivenThenResolvesToCase(string $token, Operator $operator): void
    {
        /** @Given a canonical token and the operator it maps to */

        /** @When resolving the operator from the token */
        $actual = Operator::from($token);

        /** @Then the resolved operator is the expected case */
        self::assertSame($operator, $actual);
    }

    #[DataProvider('operatorCases')]
    public function testValueWhenCaseGivenThenExposesCanonicalToken(string $token, Operator $operator): void
    {
        /** @Given a comparison operator and its canonical token */

        /** @When reading the backing value */
        $actual = $operator->value;

        /** @Then the value equals the canonical token */
        self::assertSame($token, $actual);
    }

    #[DataProvider('multiValuedCases')]
    public function testIsMultiValuedWhenOperatorGivenThenTellsWhetherItComparesAgainstAList(
        Operator $operator,
        bool $multiValued
    ): void {
        /** @Given a comparison operator and whether it compares against a value list */

        /** @When asking whether the operator is multi-valued */
        $actual = $operator->isMultiValued();

        /** @Then the answer matches the expected outcome */
        self::assertSame($multiValued, $actual);
    }

    public static function operatorCases(): array
    {
        return [
            'IN'                    => ['operator' => Operator::IN, 'token' => '=in='],
            'EQUAL'                 => ['operator' => Operator::EQUAL, 'token' => '=='],
            'NOT_IN'                => ['operator' => Operator::NOT_IN, 'token' => '=out='],
            'LESS_THAN'             => ['operator' => Operator::LESS_THAN, 'token' => '=lt='],
            'NOT_EQUAL'             => ['operator' => Operator::NOT_EQUAL, 'token' => '!='],
            'STARTS_WITH'           => ['operator' => Operator::STARTS_WITH, 'token' => '=sw='],
            'GREATER_THAN'          => ['operator' => Operator::GREATER_THAN, 'token' => '=gt='],
            'LESS_THAN_OR_EQUAL'    => ['operator' => Operator::LESS_THAN_OR_EQUAL, 'token' => '=le='],
            'GREATER_THAN_OR_EQUAL' => ['operator' => Operator::GREATER_THAN_OR_EQUAL, 'token' => '=ge=']
        ];
    }

    public static function multiValuedCases(): array
    {
        return [
            'IN'                    => ['operator' => Operator::IN, 'multiValued' => true],
            'EQUAL'                 => ['operator' => Operator::EQUAL, 'multiValued' => false],
            'NOT_IN'                => ['operator' => Operator::NOT_IN, 'multiValued' => true],
            'LESS_THAN'             => ['operator' => Operator::LESS_THAN, 'multiValued' => false],
            'NOT_EQUAL'             => ['operator' => Operator::NOT_EQUAL, 'multiValued' => false],
            'STARTS_WITH'           => ['operator' => Operator::STARTS_WITH, 'multiValued' => false],
            'GREATER_THAN'          => ['operator' => Operator::GREATER_THAN, 'multiValued' => false],
            'LESS_THAN_OR_EQUAL'    => ['operator' => Operator::LESS_THAN_OR_EQUAL, 'multiValued' => false],
            'GREATER_THAN_OR_EQUAL' => ['operator' => Operator::GREATER_THAN_OR_EQUAL, 'multiValued' => false]
        ];
    }
}
