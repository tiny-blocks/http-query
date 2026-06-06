<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Schema;

final class SchemaTest extends TestCase
{
    public function testDefaultThenCarriesTheCanonicalPageKey(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When reading the page key */
        $actual = $schema->pageKey();

        /** @Then it is the canonical page key */
        self::assertSame('page', $actual);
    }

    public function testDefaultThenCarriesTheCanonicalSortKey(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When reading the sort key */
        $actual = $schema->sortKey();

        /** @Then it is the canonical sort key */
        self::assertSame('sort', $actual);
    }

    public function testDefaultThenCarriesTheCanonicalCursorKey(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When reading the cursor key */
        $actual = $schema->cursorKey();

        /** @Then it is the canonical cursor key */
        self::assertSame('cursor', $actual);
    }

    public function testDefaultThenCarriesTheCanonicalFilterKey(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When reading the filter key */
        $actual = $schema->filterKey();

        /** @Then it is the canonical filter key */
        self::assertSame('filter', $actual);
    }

    public function testDefaultThenCarriesTheCanonicalMaxPerPage(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When reading the maximum page size */
        $actual = $schema->maxPerPage();

        /** @Then it is the canonical maximum page size */
        self::assertSame(100, $actual);
    }

    public function testDefaultThenCarriesTheCanonicalPerPageKey(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When reading the per-page key */
        $actual = $schema->perPageKey();

        /** @Then it is the canonical per-page key */
        self::assertSame('per_page', $actual);
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

    public function testWithPageKeyWhenReplacedThenCopyCarriesItAndOriginalIsUnchanged(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When replacing the page key */
        $copy = $schema->withPageKey(pageKey: 'p');

        /** @Then the copy carries the new page key */
        self::assertSame('p', $copy->pageKey());

        /** @And the original schema keeps the canonical page key */
        self::assertSame('page', $schema->pageKey());
    }

    public function testWithSortKeyWhenReplacedThenCopyCarriesItAndOriginalIsUnchanged(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When replacing the sort key */
        $copy = $schema->withSortKey(sortKey: 'order');

        /** @Then the copy carries the new sort key */
        self::assertSame('order', $copy->sortKey());

        /** @And the original schema keeps the canonical sort key */
        self::assertSame('sort', $schema->sortKey());
    }

    public function testWithCursorKeyWhenReplacedThenCopyCarriesItAndOriginalIsUnchanged(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When replacing the cursor key */
        $copy = $schema->withCursorKey(cursorKey: 'after');

        /** @Then the copy carries the new cursor key */
        self::assertSame('after', $copy->cursorKey());

        /** @And the original schema keeps the canonical cursor key */
        self::assertSame('cursor', $schema->cursorKey());
    }

    public function testWithFilterKeyWhenReplacedThenCopyCarriesItAndOriginalIsUnchanged(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When replacing the filter key */
        $copy = $schema->withFilterKey(filterKey: 'q');

        /** @Then the copy carries the new filter key */
        self::assertSame('q', $copy->filterKey());

        /** @And the original schema keeps the canonical filter key */
        self::assertSame('filter', $schema->filterKey());
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

    public function testWithPerPageKeyWhenReplacedThenCopyCarriesItAndOriginalIsUnchanged(): void
    {
        /** @Given the default schema */
        $schema = Schema::default();

        /** @When replacing the per-page key */
        $copy = $schema->withPerPageKey(perPageKey: 'size');

        /** @Then the copy carries the new per-page key */
        self::assertSame('size', $copy->perPageKey());

        /** @And the original schema keeps the canonical per-page key */
        self::assertSame('per_page', $schema->perPageKey());
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
