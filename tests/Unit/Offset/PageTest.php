<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Offset;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\Collection\KeyPreservation;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Exceptions\TotalIsNegative;
use TinyBlocks\HttpQuery\NavigationTarget;
use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Offset\Pagination;

final class PageTest extends TestCase
{
    public function testTotalWhenPageGivenThenReturnsTheTotalElementCount(): void
    {
        /** @Given a criteria on the first page with a page size of twenty */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '1', 'size' => '20']])
        );

        /** @When building the page from the total element count */
        $page = $criteria->page(total: 480, items: ['a', 'b']);

        /** @Then the total is the count provided across every page */
        self::assertSame(480, $page->total());
    }

    public function testItemsWhenPageGivenThenCarriesTheProvidedItems(): void
    {
        /** @Given a criteria on the third page with a page size of twenty */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @When building the page and reading its items */
        $page = $criteria->page(total: 480, items: ['a', 'b']);

        /** @Then the items carry the provided elements */
        self::assertSame(['a', 'b'], $page->items()->toArray(keyPreservation: KeyPreservation::DISCARD));
    }

    public function testFromWhenTotalIsNegativeThenThrowsTotalIsNegative(): void
    {
        /** @Given a criteria on the first page */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '1', 'size' => '20']])
        );

        /** @Then an exception indicating the total is negative is raised */
        $this->expectException(TotalIsNegative::class);
        $this->expectExceptionMessage('Total');

        /** @When building a page from a negative total */
        $criteria->page(total: -1, items: []);
    }

    public function testNavigationWhenTotalIsZeroThenThereIsNoPage(): void
    {
        /** @Given a criteria on the first page */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '1', 'size' => '20']])
        );

        /** @When building the page from a total of zero */
        $page = $criteria->page(total: 0, items: []);

        /** @Then there are no pages */
        self::assertSame(0, $page->totalPages());

        /** @And the page reports no next page */
        self::assertFalse($page->hasNext());

        /** @And the first pagination is null */
        self::assertNull($page->first());

        /** @And the last pagination is null */
        self::assertNull($page->last());

        /** @And the metadata carries a zero total and zero total pages */
        self::assertSame([
            'total'        => 0,
            'per_page'     => 20,
            'total_pages'  => 0,
            'current_page' => 1,
            'has_next'     => false,
            'has_previous' => false
        ], $page->metadata());
    }

    public function testNavigationWhenLastPageGivenThenHasNoNextPage(): void
    {
        /** @Given a criteria on the last page */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '24', 'size' => '20']])
        );

        /** @When building the page from the total element count */
        $page = $criteria->page(total: 480, items: ['a', 'b']);

        /** @Then the page reports no next page */
        self::assertFalse($page->hasNext());

        /** @And the page has a previous page */
        self::assertTrue($page->hasPrevious());

        /** @And the next pagination is null */
        self::assertNull($page->next());

        /** @And the last pagination points at the last page */
        self::assertSame(24, $page->last()?->page());
    }

    public function testNavigationWhenFirstPageGivenThenHasNoPreviousPage(): void
    {
        /** @Given a criteria on the first page */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '1', 'size' => '20']])
        );

        /** @When building the page from the total element count */
        $page = $criteria->page(total: 480, items: ['a', 'b']);

        /** @Then the page reports no previous page */
        self::assertFalse($page->hasPrevious());

        /** @And the page has a next page */
        self::assertTrue($page->hasNext());

        /** @And the previous pagination is null */
        self::assertNull($page->previous());

        /** @And the first pagination points at the first page */
        self::assertSame(1, $page->first()?->page());

        /** @And the next pagination points at the second page */
        self::assertSame(2, $page->next()?->page());

        /** @And the last pagination points at the last page */
        self::assertSame(24, $page->last()?->page());
    }

    public function testTotalPagesWhenTotalIsNotAMultipleOfPerPageThenRoundsUp(): void
    {
        /** @Given a criteria on the first page with a page size of twenty */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '1', 'size' => '20']])
        );

        /** @When building the page from a total that is not a multiple of the page size */
        $page = $criteria->page(total: 21, items: ['a', 'b']);

        /** @Then the total number of pages rounds the division up */
        self::assertSame(2, $page->totalPages());
    }

    public function testMetadataWhenMiddlePageGivenThenCarriesEveryFlagAndCount(): void
    {
        /** @Given a criteria on a middle page with a page size of twenty */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @When building the page from the total element count */
        $page = $criteria->page(total: 480, items: ['a', 'b']);

        /** @Then the metadata carries every entry in grouped key order (counts and sizes, then the boolean flags) */
        self::assertSame([
            'total'        => 480,
            'per_page'     => 20,
            'total_pages'  => 24,
            'current_page' => 3,
            'has_next'     => true,
            'has_previous' => true
        ], $page->metadata());
    }

    public function testNavigationWhenFirstPageGivenThenOmitsThePreviousRelation(): void
    {
        /** @Given a criteria on the first page of a multi-page result */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '1', 'size' => '20']])
        );

        /** @And a page on that first page */
        $page = $criteria->page(total: 480, items: []);

        /** @When reading the relations of the navigation targets */
        $relations = $page->navigation()->targets()
            ->map(transformations: static fn(NavigationTarget $target): string => $target->relation()->value)
            ->toArray(keyPreservation: KeyPreservation::DISCARD);

        /** @Then it lists the first, next, and last relations while omitting the previous relation */
        self::assertSame(['first', 'next', 'last'], $relations);
    }

    public function testToResponseWhenMiddlePageGivenThenRendersBodyAndLinkHeader(): void
    {
        /** @Given a criteria on the third page with a page size of twenty */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @And a page built over items keyed by their positions */
        $page = $criteria->page(total: 480, items: [5 => 'a', 9 => 'b']);

        /** @When rendering the page as a JSON:API response over the orders base URI */
        $response = $page->toResponse(baseUri: '/v1/orders');

        /** @Then the response body carries the zero-based data, the meta, and the navigation links */
        self::assertSame([
            'data'  => ['a', 'b'],
            'meta'  => [
                'total'        => 480,
                'per_page'     => 20,
                'total_pages'  => 24,
                'current_page' => 3,
                'has_next'     => true,
                'has_previous' => true
            ],
            'links' => [
                'self'  => '/v1/orders?page[number]=3&page[size]=20',
                'first' => '/v1/orders?page[number]=1&page[size]=20',
                'prev'  => '/v1/orders?page[number]=2&page[size]=20',
                'next'  => '/v1/orders?page[number]=4&page[size]=20',
                'last'  => '/v1/orders?page[number]=24&page[size]=20'
            ]
        ], json_decode($response->getBody()->getContents(), true));

        /** @And the Link header folds every relation in navigation order */
        self::assertSame(implode(', ', [
            '</v1/orders?page[number]=3&page[size]=20>; rel="self"',
            '</v1/orders?page[number]=1&page[size]=20>; rel="first"',
            '</v1/orders?page[number]=2&page[size]=20>; rel="prev"',
            '</v1/orders?page[number]=4&page[size]=20>; rel="next"',
            '</v1/orders?page[number]=24&page[size]=20>; rel="last"'
        ]), $response->getHeaderLine('Link'));
    }

    public function testNavigationWhenMiddlePageGivenThenExposesEverySurroundingPage(): void
    {
        /** @Given a criteria on a middle page with a page size of twenty */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @When building the page from the total element count */
        $page = $criteria->page(total: 480, items: ['a', 'b']);

        /** @Then the current page number is preserved */
        self::assertSame(3, $page->currentPage());

        /** @And the total number of pages is derived from the total and the page size */
        self::assertSame(24, $page->totalPages());

        /** @And the offset is derived from the page and the page size */
        self::assertSame(40, $page->offset());

        /** @And the next pagination points at the fourth page */
        self::assertSame(4, $page->next()?->page());

        /** @And the previous pagination points at the second page */
        self::assertSame(2, $page->previous()?->page());

        /** @And the first pagination points at the first page */
        self::assertSame(1, $page->first()?->page());

        /** @And the last pagination points at the last page */
        self::assertSame(24, $page->last()?->page());
    }

    public function testNavigationWhenMiddlePageGivenThenListsFirstPreviousNextAndLastTargets(): void
    {
        /** @Given a criteria on a middle page of a multi-page result */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @And a page on that middle page */
        $page = $criteria->page(total: 480, items: []);

        /** @When reading the relations of the navigation targets */
        $relations = $page->navigation()->targets()
            ->map(transformations: static fn(NavigationTarget $target): string => $target->relation()->value)
            ->toArray(keyPreservation: KeyPreservation::DISCARD);

        /** @Then it lists the first, previous, next, and last relations in semantic order */
        self::assertSame(['first', 'prev', 'next', 'last'], $relations);
    }

    public function testNavigationWhenMiddlePageGivenThenTheFirstTargetPointsAtTheFirstPage(): void
    {
        /** @Given a criteria on a middle page of a multi-page result */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @And a page on that middle page */
        $page = $criteria->page(total: 480, items: []);

        /** @When reading the first navigation target */
        $target = $page->navigation()->targets()->first();

        /** @Then the first target is the first page reached through the first relation */
        self::assertEquals(
            NavigationTarget::to(target: Pagination::fromPage(page: 1, perPage: 20), relation: LinkRelation::FIRST),
            $target
        );
    }
}
