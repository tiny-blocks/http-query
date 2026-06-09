<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Operator;

final class ComparisonTest extends TestCase
{
    public function testFieldThenReturnsTheComparedField(): void
    {
        /** @Given an equality comparison on a field */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When reading the compared field */
        $field = $comparison->field();

        /** @Then it returns the field being compared */
        self::assertSame('status', $field);
    }

    public function testValuesThenReturnsTheComparedValues(): void
    {
        /** @Given an IN comparison carrying several values */
        $comparison = Comparison::of(field: 'role', values: ['admin', 'user'], operator: Operator::IN);

        /** @When reading the compared values */
        $values = $comparison->values();

        /** @Then it returns the values compared against the field in order */
        self::assertSame(['admin', 'user'], $values);
    }

    public function testIsEmptyThenReportsItIsNotEmpty(): void
    {
        /** @Given an equality comparison */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When checking whether it is empty */
        $isEmpty = $comparison->isEmpty();

        /** @Then it reports itself as not empty */
        self::assertFalse($isEmpty);
    }

    public function testOperatorThenReturnsTheComparisonOperator(): void
    {
        /** @Given an equality comparison */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When reading the comparison operator */
        $operator = $comparison->operator();

        /** @Then it returns the operator the comparison carries */
        self::assertSame(Operator::EQUAL, $operator);
    }

    public function testHasFieldWhenFieldMatchesThenIsTrue(): void
    {
        /** @Given an equality comparison on a field */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When asking whether it targets that field */
        $hasField = $comparison->hasField(field: 'status');

        /** @Then it reports that it targets the field */
        self::assertTrue($hasField);
    }

    public function testHasFieldWhenFieldDiffersThenIsFalse(): void
    {
        /** @Given an equality comparison on a field */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When asking whether it targets another field */
        $hasField = $comparison->hasField(field: 'total');

        /** @Then it reports that it does not target the field */
        self::assertFalse($hasField);
    }

    public function testFirstValueWhenMultipleValuesThenReturnsTheFirst(): void
    {
        /** @Given an IN comparison carrying several values */
        $comparison = Comparison::of(field: 'role', values: ['admin', 'user'], operator: Operator::IN);

        /** @When reading the first compared value */
        $firstValue = $comparison->firstValue();

        /** @Then it returns the first of the compared values */
        self::assertSame('admin', $firstValue);
    }

    public function testHasOperatorWhenOperatorMatchesThenIsTrue(): void
    {
        /** @Given an equality comparison */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When asking whether it carries the equality operator */
        $hasOperator = $comparison->hasOperator(operator: Operator::EQUAL);

        /** @Then it reports that it carries the operator */
        self::assertTrue($hasOperator);
    }

    public function testHasOperatorWhenOperatorDiffersThenIsFalse(): void
    {
        /** @Given an equality comparison */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When asking whether it carries another operator */
        $hasOperator = $comparison->hasOperator(operator: Operator::NOT_EQUAL);

        /** @Then it reports that it does not carry the operator */
        self::assertFalse($hasOperator);
    }
}
