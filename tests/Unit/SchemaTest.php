<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Internal\Conjunction;
use TinyBlocks\HttpQuery\Schema;

final class SchemaTest extends TestCase
{
    public function testCreateThenAppliesTheDefaultPageSize(): void
    {
        /** @Given a schema created with an empty contract */
        $schema = Schema::create();

        /** @When asking for a page size with no requested value */
        $actual = $schema->pageSizeFor(requested: null);

        /** @Then it returns the canonical default page size */
        self::assertSame(20, $actual);
    }

    public function testDefaultThenAppliesTheDefaultPageSize(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When asking for a page size with no requested value */
        $actual = $schema->pageSizeFor(requested: null);

        /** @Then it returns the canonical default page size */
        self::assertSame(20, $actual);
    }

    public function testPageSizeForWhenAtMaximumThenReturnsTheMaximumSize(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When asking for a page size equal to the maximum */
        $actual = $schema->pageSizeFor(requested: 100);

        /** @Then it returns the maximum page size */
        self::assertSame(100, $actual);
    }

    public function testPageSizeForWhenWithinMaximumThenReturnsTheRequestedSize(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When asking for a page size within the maximum */
        $actual = $schema->pageSizeFor(requested: 50);

        /** @Then it returns the requested page size */
        self::assertSame(50, $actual);
    }

    public function testMaxPerPageWhenRaisedThenTheCopyAcceptsTheLargerSize(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When raising the maximum page size and asking for the larger size */
        $actual = $schema->maxPerPage(maxPerPage: 250)->pageSizeFor(requested: 250);

        /** @Then the larger size is accepted */
        self::assertSame(250, $actual);
    }

    public function testDefaultPerPageWhenLoweredThenTheCopyAppliesIt(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When lowering the default page size and asking with no requested value */
        $actual = $schema->defaultPerPage(defaultPerPage: 5)->pageSizeFor(requested: null);

        /** @Then the lowered default page size is applied */
        self::assertSame(5, $actual);
    }

    public function testBoundsWhenBothAtMinimumThenAcceptsTheMinimumSize(): void
    {
        /** @Given a schema with the default and maximum page sizes lowered to the minimum */
        $schema = Schema::create()->defaultPerPage(defaultPerPage: 1)->maxPerPage(maxPerPage: 1);

        /** @When asking for a page size of one */
        $actual = $schema->pageSizeFor(requested: 1);

        /** @Then the minimum page size is accepted */
        self::assertSame(1, $actual);
    }

    public function testPageSizeForWhenAboveMaximumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size <101> must be less than or equal to 100.');

        /** @When asking for a page size above the maximum */
        $schema->pageSizeFor(requested: 101);
    }

    public function testMaxPerPageWhenBelowMinimumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size <0> must be greater than or equal to 1.');

        /** @When lowering the maximum page size below the minimum */
        $schema->maxPerPage(maxPerPage: 0);
    }

    public function testDefaultPerPageWhenAboveMaximumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size <150> must be less than or equal to 100.');

        /** @When raising the default page size above the maximum */
        $schema->defaultPerPage(defaultPerPage: 150);
    }

    public function testDefaultPerPageWhenBelowMinimumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size <0> must be greater than or equal to 1.');

        /** @When lowering the default page size below the minimum */
        $schema->defaultPerPage(defaultPerPage: 0);
    }

    public function testConstructorWhenInvokedThroughReflectionThenInstantiatesTheStaticOnlyConjunction(): void
    {
        /** @Given an uninitialized instance of the static-only conjunction flattener */
        $flattener = new ReflectionClass(Conjunction::class)->newInstanceWithoutConstructor();

        /** @When invoking its otherwise-uncallable private constructor */
        new ReflectionMethod(Conjunction::class, '__construct')->invoke($flattener);

        /** @Then the static-only conjunction flattener is instantiated */
        self::assertInstanceOf(Conjunction::class, $flattener);
    }
}
