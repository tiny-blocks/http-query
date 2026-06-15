<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Direction;

final class DirectionTest extends TestCase
{
    #[DataProvider('directionCases')]
    public function testFromWhenTokenGivenThenResolvesToCase(string $token, Direction $direction): void
    {
        /** @Given a canonical token and the direction it maps to */

        /** @When resolving the direction from the token */
        $actual = Direction::from($token);

        /** @Then the resolved direction is the expected case */
        self::assertSame($direction, $actual);
    }

    #[DataProvider('directionCases')]
    public function testValueWhenCaseGivenThenExposesCanonicalToken(string $token, Direction $direction): void
    {
        /** @Given a sort direction and its canonical token */

        /** @When reading the backing value */
        $actual = $direction->value;

        /** @Then the value equals the canonical token */
        self::assertSame($token, $actual);
    }

    #[DataProvider('prefixCases')]
    public function testPrefixWhenCaseGivenThenReturnsTheSortExpressionPrefix(
        string $prefix,
        Direction $direction
    ): void {
        /** @Given a sort direction and its sort-expression prefix */

        /** @When reading the prefix */
        $actual = $direction->prefix();

        /** @Then the prefix matches the expected token */
        self::assertSame($prefix, $actual);
    }

    public static function prefixCases(): array
    {
        return [
            'ASCENDING'  => ['direction' => Direction::ASCENDING, 'prefix' => ''],
            'DESCENDING' => ['direction' => Direction::DESCENDING, 'prefix' => '-']
        ];
    }

    public static function directionCases(): array
    {
        return [
            'ASCENDING'  => ['direction' => Direction::ASCENDING, 'token' => 'asc'],
            'DESCENDING' => ['direction' => Direction::DESCENDING, 'token' => 'desc']
        ];
    }
}
