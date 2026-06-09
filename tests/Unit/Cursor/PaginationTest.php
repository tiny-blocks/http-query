<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Cursor;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Cursor\Pagination;
use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;

final class PaginationTest extends TestCase
{
    public function testFromWhenPageSizeIsTheMinimumThenCarriesThatLimit(): void
    {
        /** @Given a cursor pagination built from an absent cursor and the minimum page size of 1 */
        $pagination = Pagination::from(cursor: Token::none(), perPage: 1);

        /** @When reading the page size limit */
        $limit = $pagination->limit();

        /** @Then it carries the minimum page size */
        self::assertSame(1, $limit);
    }

    public function testToQueryStringWhenCursorAbsentThenRendersOnlyTheSize(): void
    {
        /** @Given a cursor pagination carrying an absent cursor and a page size */
        $pagination = Pagination::from(cursor: Token::none(), perPage: 10);

        /** @When rendering it as a query string */
        $queryString = $pagination->toQueryString();

        /** @Then it renders only the page size in the JSON:API page family */
        self::assertSame('page[size]=10', $queryString);
    }

    public function testFromWhenAbsentCursorGivenThenCarriesLimitAndAbsentCursor(): void
    {
        /** @Given a cursor pagination built from an absent cursor and a page size of 20 */
        $pagination = Pagination::from(cursor: Token::none(), perPage: 20);

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
        $cursor = Token::none();

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);

        /** @When building a cursor pagination with a page size below the minimum */
        Pagination::from(cursor: $cursor, perPage: 0);
    }

    public function testToQueryStringWhenCursorPresentThenRendersCursorAndSize(): void
    {
        /** @Given a cursor pagination carrying an incoming cursor and a page size */
        $pagination = Pagination::from(cursor: Token::from(token: 'abc'), perPage: 10);

        /** @When rendering it as a query string */
        $queryString = $pagination->toQueryString();

        /** @Then it renders the cursor token followed by the page size in the JSON:API page family */
        self::assertSame('page[cursor]=abc&page[size]=10', $queryString);
    }

    public function testFromWhenIncomingCursorGivenThenCarriesLimitAndTheCursorToken(): void
    {
        /** @Given a cursor pagination built from an incoming cursor and a page size of 50 */
        $pagination = Pagination::from(cursor: Token::from(token: 'abc'), perPage: 50);

        /** @When reading the page size limit */
        $limit = $pagination->limit();

        /** @Then it carries the requested page size */
        self::assertSame(50, $limit);

        /** @And its cursor renders the incoming token */
        self::assertSame('abc', $pagination->cursor()->toString());
    }
}
