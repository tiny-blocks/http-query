<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Collection\KeyPreservation;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\NavigationTarget;
use TinyBlocks\HttpQuery\Pagination;
use TinyBlocks\HttpQuery\Slice;

final class SliceTest extends TestCase
{
    public function testNavigationWhenExtraElementFetchedThenHasNextAndTrimsItems(): void
    {
        /** @Given a pagination on the second page with a page size of three */
        $pagination = Pagination::fromPage(page: 2, perPage: 3);

        /** @And items fetched for the page size plus one */
        $items = Collection::createFrom(elements: ['a', 'b', 'c', 'd']);

        /** @When building the slice from the items and the pagination */
        $slice = Slice::from(items: $items, pagination: $pagination);

        /** @Then the slice reports a next page */
        self::assertTrue($slice->hasNext());

        /** @And the items are trimmed to the page size */
        self::assertSame(['a', 'b', 'c'], $slice->items()->toArray(keyPreservation: KeyPreservation::DISCARD));

        /** @And the current page number is preserved */
        self::assertSame(2, $slice->currentPage());

        /** @And the slice has a previous page */
        self::assertTrue($slice->hasPrevious());

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

    public function testOffsetWhenSecondPageGivenThenReturnsTheZeroBasedOffset(): void
    {
        /** @Given a pagination on the second page with a page size of three */
        $pagination = Pagination::fromPage(page: 2, perPage: 3);

        /** @And items fetched for the page size plus one */
        $items = Collection::createFrom(elements: ['a', 'b', 'c', 'd']);

        /** @When building the slice from the items and the pagination */
        $slice = Slice::from(items: $items, pagination: $pagination);

        /** @Then the offset is derived from the page and the page size */
        self::assertSame(3, $slice->offset());
    }

    public function testNavigationWhenFirstPageWithoutExtraElementThenListsNoRelation(): void
    {
        /** @Given a slice on the first page fetched within the page size */
        $slice = Slice::from(
            items: Collection::createFrom(elements: ['a', 'b', 'c']),
            pagination: Pagination::fromPage(page: 1, perPage: 3)
        );

        /** @When reading the relations of the navigation targets */
        $relations = $slice->navigation()->targets()
            ->map(transformations: static fn(NavigationTarget $target): string => $target->relation()->value)
            ->toArray(keyPreservation: KeyPreservation::DISCARD);

        /** @Then it lists no relation */
        self::assertSame([], $relations);
    }

    public function testNavigationWhenMiddlePageGivenThenListsPreviousAndNextRelations(): void
    {
        /** @Given a slice on a middle page fetched for the page size plus one */
        $slice = Slice::from(
            items: Collection::createFrom(elements: ['a', 'b', 'c', 'd']),
            pagination: Pagination::fromPage(page: 2, perPage: 3)
        );

        /** @When reading the relations of the navigation targets */
        $relations = $slice->navigation()->targets()
            ->map(transformations: static fn(NavigationTarget $target): string => $target->relation()->value)
            ->toArray(keyPreservation: KeyPreservation::DISCARD);

        /** @Then it lists the previous and next relations in that order */
        self::assertSame(['prev', 'next'], $relations);
    }

    public function testNavigationWhenNoExtraElementOnFirstPageThenNoNextAndNoPrevious(): void
    {
        /** @Given a pagination on the first page with a page size of three */
        $pagination = Pagination::fromPage(page: 1, perPage: 3);

        /** @And items fetched within the page size */
        $items = Collection::createFrom(elements: ['a', 'b', 'c']);

        /** @When building the slice from the items and the pagination */
        $slice = Slice::from(items: $items, pagination: $pagination);

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
    }

    public function testNavigationWhenMiddlePageGivenThenTheNextTargetPointsAtTheFollowingPage(): void
    {
        /** @Given a slice on a middle page fetched for the page size plus one */
        $slice = Slice::from(
            items: Collection::createFrom(elements: ['a', 'b', 'c', 'd']),
            pagination: Pagination::fromPage(page: 2, perPage: 3)
        );

        /** @When reading the last navigation target */
        $target = $slice->navigation()->targets()->last();

        /** @Then the last target is the third page reached through the next relation */
        self::assertEquals(
            NavigationTarget::to(target: Pagination::fromPage(page: 3, perPage: 3), relation: LinkRelation::NEXT),
            $target
        );
    }
}
