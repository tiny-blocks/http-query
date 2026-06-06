<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\CursorPagination;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Schema;

final class CursorPaginationTest extends TestCase
{
    public function testFromWhenPageSizeIsTheMinimumThenCarriesThatLimit(): void
    {
        /** @Given a cursor pagination built from an absent cursor and the minimum page size of 1 */
        $pagination = CursorPagination::from(cursor: Cursor::none(), perPage: 1);

        /** @When reading the page size limit */
        $limit = $pagination->limit();

        /** @Then it carries the minimum page size */
        self::assertSame(1, $limit);
    }

    public function testToQueryStringWhenCursorAbsentThenRendersOnlyPerPage(): void
    {
        /** @Given a cursor pagination carrying an absent cursor and a page size */
        $pagination = CursorPagination::from(cursor: Cursor::none(), perPage: 10);

        /** @When rendering it as a query string against the default schema */
        $queryString = $pagination->toQueryString(schema: Schema::default());

        /** @Then it renders only the page size */
        self::assertSame('per_page=10', $queryString);
    }

    public function testFromWhenAbsentCursorGivenThenCarriesLimitAndAbsentCursor(): void
    {
        /** @Given a cursor pagination built from an absent cursor and a page size of 20 */
        $pagination = CursorPagination::from(cursor: Cursor::none(), perPage: 20);

        /** @When reading the page size limit */
        $limit = $pagination->limit();

        /** @Then it carries the requested page size */
        self::assertSame(20, $limit);

        /** @And its cursor is absent */
        self::assertTrue($pagination->cursor()->isAbsent());
    }

    public function testFromWhenPageSizeBelowMinimumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given an absent cursor */
        $cursor = Cursor::none();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);

        /** @When building a cursor pagination with a page size below the minimum */
        CursorPagination::from(cursor: $cursor, perPage: 0);
    }

    public function testToQueryStringWhenCursorPresentThenRendersCursorAndPerPage(): void
    {
        /** @Given a cursor pagination carrying an incoming cursor and a page size */
        $pagination = CursorPagination::from(cursor: Cursor::from(token: 'abc'), perPage: 10);

        /** @When rendering it as a query string against the default schema */
        $queryString = $pagination->toQueryString(schema: Schema::default());

        /** @Then it renders the cursor token followed by the page size */
        self::assertSame('cursor=abc&per_page=10', $queryString);
    }

    public function testFromWhenIncomingCursorGivenThenCarriesLimitAndTheCursorToken(): void
    {
        /** @Given a cursor pagination built from an incoming cursor and a page size of 50 */
        $pagination = CursorPagination::from(cursor: Cursor::from(token: 'abc'), perPage: 50);

        /** @When reading the page size limit */
        $limit = $pagination->limit();

        /** @Then it carries the requested page size */
        self::assertSame(50, $limit);

        /** @And its cursor renders the incoming token */
        self::assertSame('abc', $pagination->cursor()->toString());
    }
}
