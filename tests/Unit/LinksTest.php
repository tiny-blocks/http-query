<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\Collection\Collection;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\CursorPage;
use TinyBlocks\HttpQuery\CursorPagination;
use TinyBlocks\HttpQuery\Internal\Uri;
use TinyBlocks\HttpQuery\Links;

final class LinksTest extends TestCase
{
    public function testToArrayWhenPageIsTheLastThenOmitsTheNextRelation(): void
    {
        /** @Given a criteria pointing at the twenty-fourth page */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'page' => ['number' => '24', 'size' => '20']
        ]));

        /** @And a page carrying a total spanning twenty-four pages */
        $result = $criteria->offsetPage(items: Collection::createFrom(elements: range(1, 20)), total: 480);

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
        /** @Given a criteria carrying a filter and a sort at a middle page */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'sort'   => '-created_at,id',
            'page'   => ['number' => '3', 'size' => '20'],
            'filter' => 'status==paid'
        ]));

        /** @And a slice fetched for the page size plus one so a next page exists */
        $result = $criteria->offsetSlice(items: Collection::createFrom(elements: range(1, 21)));

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
        /** @Given a criteria pointing at the first page */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: []));

        /** @And a page carrying a total spanning twenty-four pages */
        $result = $criteria->offsetPage(items: Collection::createFrom(elements: range(1, 20)), total: 480);

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
        /** @Given a criteria pointing at a middle page */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'page' => ['number' => '3', 'size' => '20']
        ]));

        /** @And a slice fetched for the page size plus one so a next page exists */
        $result = $criteria->offsetSlice(items: Collection::createFrom(elements: range(1, 21)));

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
        /** @Given a criteria carrying a filter and a sort at a middle page */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'sort'   => '-created_at,id',
            'page'   => ['number' => '3', 'size' => '20'],
            'filter' => 'status==paid'
        ]));

        /** @And a page carrying a total spanning twenty-four pages */
        $result = $criteria->offsetPage(items: Collection::createFrom(elements: range(1, 20)), total: 480);

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
        /** @Given a criteria carrying a filter and a sort at a middle page */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: [
            'sort'   => '-created_at,id',
            'page'   => ['number' => '3', 'size' => '20'],
            'filter' => 'status==paid'
        ]));

        /** @And a page carrying a total spanning twenty-four pages */
        $result = $criteria->offsetPage(items: Collection::createFrom(elements: range(1, 20)), total: 480);

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

    public function testConstructorWhenInvokedThroughReflectionThenInstantiatesTheStaticOnlyUri(): void
    {
        /** @Given an uninitialized instance of the static-only URI assembler */
        $uri = new ReflectionClass(Uri::class)->newInstanceWithoutConstructor();

        /** @When invoking its otherwise-uncallable private constructor */
        new ReflectionMethod(Uri::class, '__construct')->invoke($uri);

        /** @Then the static-only URI assembler is instantiated */
        self::assertInstanceOf(Uri::class, $uri);
    }
}
