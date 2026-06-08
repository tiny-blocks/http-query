<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Schema;

final class SchemaTest extends TestCase
{
    public function testDefaultThenCarriesTheCanonicalMaxPerPage(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When reading the maximum page size */
        $actual = $schema->maxPerPage();

        /** @Then it is the canonical maximum page size */
        self::assertSame(100, $actual);
    }

    public function testDefaultThenCarriesTheCanonicalDefaultPerPage(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When reading the default page size */
        $actual = $schema->defaultPerPage();

        /** @Then it is the canonical default page size */
        self::assertSame(20, $actual);
    }

    public function testWithBoundsWhenBothAtMinimumThenSchemaCarriesThem(): void
    {
        /** @Given a schema with the default and maximum page sizes lowered to the minimum */
        $schema = Schema::default()->withDefaultPerPage(defaultPerPage: 1)->withMaxPerPage(maxPerPage: 1);

        /** @When reading the maximum page size */
        $actual = $schema->maxPerPage();

        /** @Then it is the minimum page size */
        self::assertSame(1, $actual);

        /** @And the default page size is the minimum too */
        self::assertSame(1, $schema->defaultPerPage());
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

    public function testPageSizeForWhenAboveMaximumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size');

        /** @When asking for a page size above the maximum */
        $schema->pageSizeFor(requested: 101);
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

    public function testWithMaxPerPageWhenBelowMinimumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size <0> must be greater than or equal to 1.');

        /** @When lowering the maximum page size below the minimum */
        $schema->withMaxPerPage(maxPerPage: 0);
    }

    public function testWithDefaultPerPageWhenAboveMaximumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size <150> must be less than or equal to 100.');

        /** @When raising the default page size above the maximum */
        $schema->withDefaultPerPage(defaultPerPage: 150);
    }

    public function testWithDefaultPerPageWhenBelowMinimumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size <0> must be greater than or equal to 1.');

        /** @When lowering the default page size below the minimum */
        $schema->withDefaultPerPage(defaultPerPage: 0);
    }

    public function testWithMaxPerPageWhenReplacedThenCopyCarriesItAndOriginalIsUnchanged(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When replacing the maximum page size */
        $copy = $schema->withMaxPerPage(maxPerPage: 250);

        /** @Then the copy carries the new maximum page size */
        self::assertSame(250, $copy->maxPerPage());

        /** @And the original schema keeps the canonical maximum page size */
        self::assertSame(100, $schema->maxPerPage());
    }

    public function testWithDefaultPerPageWhenReplacedThenCopyCarriesItAndOriginalIsUnchanged(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When replacing the default page size */
        $copy = $schema->withDefaultPerPage(defaultPerPage: 50);

        /** @Then the copy carries the new default page size */
        self::assertSame(50, $copy->defaultPerPage());

        /** @And the original schema keeps the canonical default page size */
        self::assertSame(20, $schema->defaultPerPage());
    }
}
