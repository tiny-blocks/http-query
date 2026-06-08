<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\Collection\Collection;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\CursorPage;
use TinyBlocks\HttpQuery\CursorPagination;
use TinyBlocks\HttpQuery\Links;
use TinyBlocks\HttpQuery\OffsetPage;
use TinyBlocks\HttpQuery\OffsetPagination;
use TinyBlocks\HttpQuery\OffsetSlice;

final class LinksTest extends TestCase
{
    public function testToArrayWhenPageIsTheLastThenOmitsTheNextRelation(): void
    {
        /** @Given an offset pagination pointing at the last page */
        $pagination = OffsetPagination::fromPage(page: 24, perPage: 20);

        /** @And a criteria over that pagination */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: []))->withPagination(pagination: $pagination);

        /** @And a page carrying a total spanning twenty-four pages */
        $result = OffsetPage::from(
            items: Collection::createFrom(elements: range(1, 20)),
            total: 480,
            criteria: $criteria,
            pagination: $pagination
        );

        /** @When rendering the navigation for the last page */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $result->navigation());

        /** @Then the navigation exposes the self, first, previous, and last relations in that order */
        self::assertSame([
            'self'  => '/v1/orders?page[number]=24&page[size]=20',
            'first' => '/v1/orders?page[number]=1&page[size]=20',
            'prev'  => '/v1/orders?page[number]=23&page[size]=20',
            'last'  => '/v1/orders?page[number]=24&page[size]=20'
        ], $links->toArray());

        /** @And the navigation carries no next relation */
        self::assertArrayNotHasKey('next', $links->toArray());
    }

    public function testToArrayWhenSliceMiddleThenExposesSelfFirstPrevAndNext(): void
    {
        /** @Given an offset pagination pointing at a middle page */
        $pagination = OffsetPagination::fromPage(page: 3, perPage: 20);

        /** @And a criteria carrying a filter and a sort over that pagination */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'sort'   => '-created_at,id',
            'filter' => 'status==paid'
        ]))->withPagination(pagination: $pagination);

        /** @And a slice fetched for the page size plus one so a next page exists */
        $result = OffsetSlice::from(
            items: Collection::createFrom(elements: range(1, 21)),
            criteria: $criteria,
            pagination: $pagination
        );

        /** @When rendering the navigation for the slice */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $result->navigation());

        /** @Then the navigation exposes the self, first, previous, and next relations preserving filter and sort */
        self::assertSame([
            'self'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=3&page[size]=20',
            'first' => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=1&page[size]=20',
            'prev'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=2&page[size]=20',
            'next'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=4&page[size]=20'
        ], $links->toArray());
    }

    public function testToArrayWhenCursorResultThenExposesOnlySelfAndNext(): void
    {
        /** @Given a cursor criteria carrying an incoming cursor token and a page size of two */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'page' => ['cursor' => Cursor::fromKeys(keys: [5])->toString(), 'size' => '2']
        ]));

        /** @And a cursor page fetched for the page size plus one with no incoming cursor */
        $result = CursorPage::from(
            items: Collection::createFrom(elements: [10, 20, 30]),
            keysOf: static fn(mixed $element): array => [$element],
            criteria: $criteria,
            pagination: CursorPagination::from(cursor: Cursor::none(), perPage: 2)
        );

        /** @When rendering the navigation for the cursor page */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $result->navigation());

        /** @Then the navigation exposes only the self and next relations */
        self::assertSame([
            'self' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', Cursor::fromKeys(keys: [5])->toString()),
            'next' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', Cursor::fromKeys(keys: [20])->toString())
        ], $links->toArray());
    }

    public function testToArrayWhenPageIsTheFirstThenOmitsThePreviousRelation(): void
    {
        /** @Given an offset pagination pointing at the first page */
        $pagination = OffsetPagination::fromPage(page: 1, perPage: 20);

        /** @And a criteria over that pagination */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: []))->withPagination(pagination: $pagination);

        /** @And a page carrying a total spanning twenty-four pages */
        $result = OffsetPage::from(
            items: Collection::createFrom(elements: range(1, 20)),
            total: 480,
            criteria: $criteria,
            pagination: $pagination
        );

        /** @When rendering the navigation for the first page */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $result->navigation());

        /** @Then the navigation exposes the self, first, next, and last relations in that order */
        self::assertSame([
            'self'  => '/v1/orders?page[number]=1&page[size]=20',
            'first' => '/v1/orders?page[number]=1&page[size]=20',
            'next'  => '/v1/orders?page[number]=2&page[size]=20',
            'last'  => '/v1/orders?page[number]=24&page[size]=20'
        ], $links->toArray());

        /** @And the navigation carries no previous relation */
        self::assertArrayNotHasKey('prev', $links->toArray());
    }

    public function testToArrayWhenSliceMiddleThenCarriesAFirstButNoLastRelation(): void
    {
        /** @Given an offset pagination pointing at a middle page */
        $pagination = OffsetPagination::fromPage(page: 3, perPage: 20);

        /** @And a criteria over that pagination */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: []))->withPagination(pagination: $pagination);

        /** @And a slice fetched for the page size plus one so a next page exists */
        $result = OffsetSlice::from(
            items: Collection::createFrom(elements: range(1, 21)),
            criteria: $criteria,
            pagination: $pagination
        );

        /** @When rendering the navigation for the slice */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $result->navigation());

        /** @Then the navigation carries a first relation */
        self::assertArrayHasKey('first', $links->toArray());

        /** @And the navigation carries no last relation */
        self::assertArrayNotHasKey('last', $links->toArray());
    }

    public function testToArrayWhenCursorResultThenCarriesNoFirstOrLastOrPreviousRelation(): void
    {
        /** @Given a cursor criteria carrying an incoming cursor token and a page size of two */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'page' => ['cursor' => Cursor::fromKeys(keys: [5])->toString(), 'size' => '2']
        ]));

        /** @And a cursor page fetched for the page size plus one with no incoming cursor */
        $result = CursorPage::from(
            items: Collection::createFrom(elements: [10, 20, 30]),
            keysOf: static fn(mixed $element): array => [$element],
            criteria: $criteria,
            pagination: CursorPagination::from(cursor: Cursor::none(), perPage: 2)
        );

        /** @When rendering the navigation for the cursor page */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $result->navigation());

        /** @Then the navigation carries no first relation */
        self::assertArrayNotHasKey('first', $links->toArray());

        /** @And the navigation carries no last relation */
        self::assertArrayNotHasKey('last', $links->toArray());

        /** @And the navigation carries no previous relation */
        self::assertArrayNotHasKey('prev', $links->toArray());
    }

    public function testToHeaderWhenPageInTheMiddleThenFoldsEveryRelationIntoOneCommaJoinedValue(): void
    {
        /** @Given an offset pagination pointing at a middle page */
        $pagination = OffsetPagination::fromPage(page: 3, perPage: 20);

        /** @And a criteria carrying a filter and a sort over that pagination */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'sort'   => '-created_at,id',
            'filter' => 'status==paid'
        ]))->withPagination(pagination: $pagination);

        /** @And a page carrying a total spanning twenty-four pages */
        $result = OffsetPage::from(
            items: Collection::createFrom(elements: range(1, 20)),
            total: 480,
            criteria: $criteria,
            pagination: $pagination
        );

        /** @When rendering the navigation as an RFC 8288 Link header */
        $header = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $result->navigation())
            ->toHeader();

        /** @Then every present relation is folded into one comma-joined Link header value */
        self::assertSame(['Link' => [implode(', ', [
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=3&page[size]=20>; rel="self"',
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=1&page[size]=20>; rel="first"',
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=2&page[size]=20>; rel="prev"',
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=4&page[size]=20>; rel="next"',
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=24&page[size]=20>; rel="last"'
        ])]], $header->toArray());
    }

    public function testToArrayWhenPageInTheMiddleThenExposesEveryRelationPreservingFilterAndSort(): void
    {
        /** @Given an offset pagination pointing at a middle page */
        $pagination = OffsetPagination::fromPage(page: 3, perPage: 20);

        /** @And a criteria carrying a filter and a sort over that pagination */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'sort'   => '-created_at,id',
            'filter' => 'status==paid'
        ]))->withPagination(pagination: $pagination);

        /** @And a page carrying a total spanning twenty-four pages */
        $result = OffsetPage::from(
            items: Collection::createFrom(elements: range(1, 20)),
            total: 480,
            criteria: $criteria,
            pagination: $pagination
        );

        /** @When rendering the navigation for the page */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $result->navigation());

        /** @Then the navigation exposes every relation in semantic order preserving filter and sort */
        self::assertSame([
            'self'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=3&page[size]=20',
            'first' => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=1&page[size]=20',
            'prev'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=2&page[size]=20',
            'next'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=4&page[size]=20',
            'last'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=24&page[size]=20'
        ], $links->toArray());
    }
}
