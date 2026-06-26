<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Offset;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Exceptions\OffsetOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageNumberOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Offset\Pagination;

final class PaginationTest extends TestCase
{
    public function testFromOffsetWhenLimitIsOneThenLimitIsOne(): void
    {
        /** @When building a pagination from the minimum limit */
        $pagination = Pagination::fromOffset(limit: 1, offset: 0);

        /** @Then the limit equals the minimum limit */
        self::assertSame(1, $pagination->limit());
    }

    public function testFromPageWhenPerPageIsOneThenLimitIsOne(): void
    {
        /** @When building a pagination from the minimum page size */
        $pagination = Pagination::fromPage(page: 1, perPage: 1);

        /** @Then the limit equals the minimum page size */
        self::assertSame(1, $pagination->limit());
    }

    public function testFromOffsetWhenZeroOffsetGivenThenPageIsOne(): void
    {
        /** @When building a pagination from a zero offset */
        $pagination = Pagination::fromOffset(limit: 20, offset: 0);

        /** @Then the page number is one */
        self::assertSame(1, $pagination->page());

        /** @And the offset is zero */
        self::assertSame(0, $pagination->offset());

        /** @And the limit is preserved */
        self::assertSame(20, $pagination->limit());
    }

    public function testFromPageWhenFirstPageGivenThenOffsetIsZero(): void
    {
        /** @When building a pagination from the first page */
        $pagination = Pagination::fromPage(page: 1, perPage: 20);

        /** @Then the page number is one */
        self::assertSame(1, $pagination->page());

        /** @And the offset is zero */
        self::assertSame(0, $pagination->offset());

        /** @And the limit equals the page size */
        self::assertSame(20, $pagination->limit());
    }

    public function testFromPageWhenThirdPageGivenThenOffsetIsDerived(): void
    {
        /** @When building a pagination from the third page */
        $pagination = Pagination::fromPage(page: 3, perPage: 20);

        /** @Then the page number is three */
        self::assertSame(3, $pagination->page());

        /** @And the offset is derived from the page and the page size */
        self::assertSame(40, $pagination->offset());

        /** @And the limit equals the page size */
        self::assertSame(20, $pagination->limit());
    }

    public function testFromOffsetWhenAlignedOffsetGivenThenPageIsDerived(): void
    {
        /** @When building a pagination from an offset aligned to the limit */
        $pagination = Pagination::fromOffset(limit: 20, offset: 40);

        /** @Then the page number is derived from the offset and the limit */
        self::assertSame(3, $pagination->page());

        /** @And the offset is preserved */
        self::assertSame(40, $pagination->offset());

        /** @And the limit is preserved */
        self::assertSame(20, $pagination->limit());
    }

    public function testFromOffsetWhenUnalignedOffsetGivenThenPageIsFloored(): void
    {
        /** @When building a pagination from an offset that is not aligned to the limit */
        $pagination = Pagination::fromOffset(limit: 20, offset: 45);

        /** @Then the page number floors the offset division and adds one */
        self::assertSame(3, $pagination->page());

        /** @And the offset is preserved */
        self::assertSame(45, $pagination->offset());

        /** @And the limit is preserved */
        self::assertSame(20, $pagination->limit());
    }

    public function testFromPageWhenPageBelowOneGivenThenPageNumberOutOfRange(): void
    {
        /** @Then an exception indicating the page number is out of range is raised */
        $this->expectException(PageNumberOutOfRange::class);
        $this->expectExceptionMessage('Page number');

        /** @When building a pagination from a page number below one */
        Pagination::fromPage(page: 0, perPage: 20);
    }

    public function testFromOffsetWhenLimitBelowOneGivenThenPageSizeOutOfRange(): void
    {
        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size');

        /** @When building a pagination from a limit below one */
        Pagination::fromOffset(limit: 0, offset: 0);
    }

    public function testFromOffsetWhenOffsetBelowZeroGivenThenOffsetOutOfRange(): void
    {
        /** @Then an exception indicating the offset is out of range is raised */
        $this->expectException(OffsetOutOfRange::class);
        $this->expectExceptionMessage('Offset');

        /** @When building a pagination from an offset below zero */
        Pagination::fromOffset(limit: 20, offset: -1);
    }

    public function testFromPageWhenPerPageBelowOneGivenThenPageSizeOutOfRange(): void
    {
        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size');

        /** @When building a pagination from a page size below one */
        Pagination::fromPage(page: 1, perPage: 0);
    }

    public function testToQueryStringWhenPageGivenThenRendersPageNumberAndSize(): void
    {
        /** @Given an offset pagination on the third page */
        $pagination = Pagination::fromPage(page: 3, perPage: 20);

        /** @When rendering it as a query string */
        $queryString = $pagination->toQueryString();

        /** @Then it renders the page number and the page size in the JSON:API page family */
        self::assertSame('page[number]=3&page[size]=20', $queryString);
    }
}
