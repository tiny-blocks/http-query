<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Offset;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\Collection\KeyPreservation;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\NavigationTarget;
use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Offset\Pagination;

final class SliceTest extends TestCase
{
    public function testOffsetWhenSecondPageGivenThenReturnsTheZeroBasedOffset(): void
    {
        /** @Given a criteria on the second page with a page size of three */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['number' => '2', 'size' => '3']]));

        /** @When building the slice from the items fetched for the page size plus one */
        $slice = $criteria->slice(items: ['a', 'b', 'c', 'd']);

        /** @Then the offset is derived from the page and the page size */
        self::assertSame(3, $slice->offset());
    }

    public function testToResponseWhenSliceGivenThenRendersBodyAndLinkHeader(): void
    {
        /** @Given a criteria on the second page with a page size of three */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['number' => '2', 'size' => '3']]));

        /** @And a slice built from items fetched for the page size plus one */
        $slice = $criteria->slice(items: ['a', 'b', 'c', 'd']);

        /** @When rendering the slice as a JSON:API response over the orders base URI */
        $response = $slice->toResponse(baseUri: '/v1/orders');

        /** @Then the response body carries the trimmed data, the meta, and the navigation links */
        self::assertSame([
            'data'  => ['a', 'b', 'c'],
            'meta'  => [
                'has_next'     => true,
                'per_page'     => 3,
                'current_page' => 2,
                'has_previous' => true
            ],
            'links' => [
                'self'  => '/v1/orders?page[number]=2&page[size]=3',
                'first' => '/v1/orders?page[number]=1&page[size]=3',
                'prev'  => '/v1/orders?page[number]=1&page[size]=3',
                'next'  => '/v1/orders?page[number]=3&page[size]=3'
            ]
        ], json_decode($response->getBody()->getContents(), true));

        /** @And the Link header folds the self, first, previous, and next relations */
        self::assertSame(implode(', ', [
            '</v1/orders?page[number]=2&page[size]=3>; rel="self"',
            '</v1/orders?page[number]=1&page[size]=3>; rel="first"',
            '</v1/orders?page[number]=1&page[size]=3>; rel="prev"',
            '</v1/orders?page[number]=3&page[size]=3>; rel="next"'
        ]), $response->getHeaderLine('Link'));
    }

    public function testNavigationWhenExtraElementFetchedThenHasNextAndTrimsItems(): void
    {
        /** @Given a criteria on the second page with a page size of three */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['number' => '2', 'size' => '3']]));

        /** @When building the slice from the items fetched for the page size plus one */
        $slice = $criteria->slice(items: ['a', 'b', 'c', 'd']);

        /** @Then the slice reports a next page */
        self::assertTrue($slice->hasNext());

        /** @And the items are trimmed to the page size */
        self::assertSame(['a', 'b', 'c'], $slice->items()->toArray(keyPreservation: KeyPreservation::DISCARD));

        /** @And the current page number is preserved */
        self::assertSame(2, $slice->currentPage());

        /** @And the slice has a previous page */
        self::assertTrue($slice->hasPrevious());

        /** @And the first pagination points at the first page */
        self::assertSame(1, $slice->first()->page());

        /** @And the next pagination points at the third page */
        self::assertSame(3, $slice->next()?->page());

        /** @And the previous pagination points at the first page */
        self::assertSame(1, $slice->previous()?->page());

        /** @And the metadata carries every navigation flag and count in length-ascending key order */
        self::assertSame([
            'has_next'     => true,
            'per_page'     => 3,
            'current_page' => 2,
            'has_previous' => true
        ], $slice->metadata());
    }

    public function testNavigationWhenNoExtraElementOnFirstPageThenNoNextAndNoPrevious(): void
    {
        /** @Given a criteria on the first page with a page size of three */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['number' => '1', 'size' => '3']]));

        /** @When building the slice from items fetched within the page size */
        $slice = $criteria->slice(items: ['a', 'b', 'c']);

        /** @Then the slice reports no next page */
        self::assertFalse($slice->hasNext());

        /** @And the items keep every fetched element */
        self::assertSame(['a', 'b', 'c'], $slice->items()->toArray(keyPreservation: KeyPreservation::DISCARD));

        /** @And the next pagination is null */
        self::assertNull($slice->next());

        /** @And the slice has no previous page */
        self::assertFalse($slice->hasPrevious());

        /** @And the previous pagination is null */
        self::assertNull($slice->previous());

        /** @And the first pagination still points at the first page */
        self::assertSame(1, $slice->first()->page());
    }

    public function testNavigationWhenMiddlePageGivenThenListsFirstPreviousAndNextRelations(): void
    {
        /** @Given a criteria on a middle page fetched for the page size plus one */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['number' => '2', 'size' => '3']]));

        /** @And a slice on that middle page */
        $slice = $criteria->slice(items: ['a', 'b', 'c', 'd']);

        /** @When reading the relations of the navigation targets */
        $relations = $slice->navigation()->targets()
            ->map(transformations: static fn(NavigationTarget $target): string => $target->relation()->value)
            ->toArray(keyPreservation: KeyPreservation::DISCARD);

        /** @Then it lists the first, previous, and next relations in that order */
        self::assertSame(['first', 'prev', 'next'], $relations);
    }

    public function testNavigationWhenMiddlePageGivenThenTheFirstTargetPointsAtTheFirstPage(): void
    {
        /** @Given a criteria on a middle page fetched for the page size plus one */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['number' => '2', 'size' => '3']]));

        /** @And a slice on that middle page */
        $slice = $criteria->slice(items: ['a', 'b', 'c', 'd']);

        /** @When reading the first navigation target */
        $target = $slice->navigation()->targets()->first();

        /** @Then the first target is the first page reached through the first relation */
        self::assertEquals(
            NavigationTarget::to(target: Pagination::fromPage(page: 1, perPage: 3), relation: LinkRelation::FIRST),
            $target
        );
    }

    public function testNavigationWhenMiddlePageGivenThenTheNextTargetPointsAtTheFollowingPage(): void
    {
        /** @Given a criteria on a middle page fetched for the page size plus one */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['number' => '2', 'size' => '3']]));

        /** @And a slice on that middle page */
        $slice = $criteria->slice(items: ['a', 'b', 'c', 'd']);

        /** @When reading the last navigation target */
        $target = $slice->navigation()->targets()->last();

        /** @Then the last target is the third page reached through the next relation */
        self::assertEquals(
            NavigationTarget::to(target: Pagination::fromPage(page: 3, perPage: 3), relation: LinkRelation::NEXT),
            $target
        );
    }

    public function testNavigationWhenFirstPageWithoutExtraElementThenListsOnlyTheFirstRelation(): void
    {
        /** @Given a criteria on the first page fetched within the page size */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['number' => '1', 'size' => '3']]));

        /** @And a slice on that first page */
        $slice = $criteria->slice(items: ['a', 'b', 'c']);

        /** @When reading the relations of the navigation targets */
        $relations = $slice->navigation()->targets()
            ->map(transformations: static fn(NavigationTarget $target): string => $target->relation()->value)
            ->toArray(keyPreservation: KeyPreservation::DISCARD);

        /** @Then it lists only the first relation */
        self::assertSame(['first'], $relations);
    }
}
