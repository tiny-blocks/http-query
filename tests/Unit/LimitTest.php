<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Limit;

final class LimitTest extends TestCase
{
    public function testPlusOneThenRaisesTheSizeByOne(): void
    {
        /** @Given a limit of twenty */
        $limit = Limit::of(size: 20);

        /** @When raising it by one for the next-page probe */
        $probe = $limit->plusOne();

        /** @Then the size is raised by one */
        self::assertSame(21, $probe->toInteger());
    }

    public function testPlusWhenAmountGivenThenRaisesTheSize(): void
    {
        /** @Given a limit of twenty */
        $limit = Limit::of(size: 20);

        /** @When raising it by a given amount */
        $raised = $limit->plus(extra: 5);

        /** @Then the size is raised by that amount */
        self::assertSame(25, $raised->toInteger());
    }

    public function testOfWhenSizeGivenThenExposesItAsAnInteger(): void
    {
        /** @Given a page size */
        $limit = Limit::of(size: 15);

        /** @Then the size is exposed as an integer */
        self::assertSame(15, $limit->toInteger());
    }
}
