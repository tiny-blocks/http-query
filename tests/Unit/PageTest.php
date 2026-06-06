<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Collection\KeyPreservation;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Exceptions\TotalIsNegative;
use TinyBlocks\HttpQuery\NavigationTarget;
use TinyBlocks\HttpQuery\Page;
use TinyBlocks\HttpQuery\Pagination;

final class PageTest extends TestCase
{
    public function testNavigationWhenTotalIsZeroThenThereIsNoPage(): void
    {
        /** @Given a pagination on the first page */
        $pagination = Pagination::fromPage(page: 1, perPage: 20);

        /** @When building the page from a total of zero */
        $page = Page::from(items: Collection::createFromEmpty(), total: 0, pagination: $pagination);

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
            'has_next'     => false,
            'per_page'     => 20,
            'total_pages'  => 0,
            'current_page' => 1,
            'has_previous' => false
        ], $page->metadata());
    }

    public function testNavigationWhenLastPageGivenThenHasNoNextPage(): void
    {
        /** @Given a pagination on the last page */
        $pagination = Pagination::fromPage(page: 24, perPage: 20);

        /** @When building the page from the total element count */
        $page = Page::from(items: Collection::createFrom(elements: ['a', 'b']), total: 480, pagination: $pagination);

        /** @Then the page reports no next page */
        self::assertFalse($page->hasNext());

        /** @And the page is the last page */
        self::assertTrue($page->isLast());

        /** @And the page has a previous page */
        self::assertTrue($page->hasPrevious());

        /** @And the next pagination is null */
        self::assertNull($page->next());

        /** @And the last pagination points at the last page */
        self::assertSame(24, $page->last()?->page());
    }

    public function testItemsWhenPageGivenThenReturnsTheSameCollection(): void
    {
        /** @Given a collection of items */
        $items = Collection::createFrom(elements: ['a', 'b']);

        /** @And a pagination on the third page */
        $pagination = Pagination::fromPage(page: 3, perPage: 20);

        /** @When building the page and reading its items */
        $page = Page::from(items: $items, total: 480, pagination: $pagination);

        /** @Then the items are the same collection that was provided */
        self::assertSame($items, $page->items());
    }

    public function testTotalWhenPageGivenThenReturnsTheTotalElementCount(): void
    {
        /** @Given a collection of items */
        $items = Collection::createFrom(elements: ['a', 'b']);

        /** @And a pagination on the first page */
        $pagination = Pagination::fromPage(page: 1, perPage: 20);

        /** @When building the page from the total element count */
        $page = Page::from(items: $items, total: 480, pagination: $pagination);

        /** @Then the total is the count provided across every page */
        self::assertSame(480, $page->total());
    }

    public function testFromWhenTotalIsNegativeThenThrowsTotalIsNegative(): void
    {
        /** @Given an empty collection of items */
        $items = Collection::createFromEmpty();

        /** @And a pagination on the first page */
        $pagination = Pagination::fromPage(page: 1, perPage: 20);

        /** @Then an exception indicating the total is negative is raised */
        $this->expectException(TotalIsNegative::class);
        $this->expectExceptionMessage('Total');

        /** @When building a page from a negative total */
        Page::from(items: $items, total: -1, pagination: $pagination);
    }

    public function testNavigationWhenFirstPageGivenThenHasNoPreviousPage(): void
    {
        /** @Given a pagination on the first page */
        $pagination = Pagination::fromPage(page: 1, perPage: 20);

        /** @When building the page from the total element count */
        $page = Page::from(items: Collection::createFrom(elements: ['a', 'b']), total: 480, pagination: $pagination);

        /** @Then the page reports no previous page */
        self::assertFalse($page->hasPrevious());

        /** @And the page is the first page */
        self::assertTrue($page->isFirst());

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
        /** @Given a pagination on the first page */
        $pagination = Pagination::fromPage(page: 1, perPage: 20);

        /** @When building the page from a total that is not a multiple of the page size */
        $page = Page::from(items: Collection::createFrom(elements: ['a', 'b']), total: 21, pagination: $pagination);

        /** @Then the total number of pages rounds the division up */
        self::assertSame(2, $page->totalPages());
    }

    public function testMetadataWhenMiddlePageGivenThenCarriesEveryFlagAndCount(): void
    {
        /** @Given a pagination on a middle page */
        $pagination = Pagination::fromPage(page: 3, perPage: 20);

        /** @When building the page from the total element count */
        $page = Page::from(items: Collection::createFrom(elements: ['a', 'b']), total: 480, pagination: $pagination);

        /** @Then the metadata carries every navigation flag and count in length-ascending key order */
        self::assertSame([
            'total'        => 480,
            'has_next'     => true,
            'per_page'     => 20,
            'total_pages'  => 24,
            'current_page' => 3,
            'has_previous' => true
        ], $page->metadata());
    }

    public function testNavigationWhenFirstPageGivenThenOmitsThePreviousRelation(): void
    {
        /** @Given a page on the first page of a multi-page result */
        $page = Page::from(
            items: Collection::createFromEmpty(),
            total: 480,
            pagination: Pagination::fromPage(page: 1, perPage: 20)
        );

        /** @When reading the relations of the navigation targets */
        $relations = $page->navigation()->targets()
            ->map(transformations: static fn(NavigationTarget $target): string => $target->relation()->value)
            ->toArray(keyPreservation: KeyPreservation::DISCARD);

        /** @Then it lists the first, next, and last relations while omitting the previous relation */
        self::assertSame(['first', 'next', 'last'], $relations);
    }

    public function testNavigationWhenMiddlePageGivenThenExposesEverySurroundingPage(): void
    {
        /** @Given a pagination on a middle page */
        $pagination = Pagination::fromPage(page: 3, perPage: 20);

        /** @When building the page from the total element count */
        $page = Page::from(items: Collection::createFrom(elements: ['a', 'b']), total: 480, pagination: $pagination);

        /** @Then the current page number is preserved */
        self::assertSame(3, $page->currentPage());

        /** @And the total number of pages is derived from the total and the page size */
        self::assertSame(24, $page->totalPages());

        /** @And the offset is derived from the page and the page size */
        self::assertSame(40, $page->offset());

        /** @And the page has a next page */
        self::assertTrue($page->hasNext());

        /** @And the page has a previous page */
        self::assertTrue($page->hasPrevious());

        /** @And the page is not the first page */
        self::assertFalse($page->isFirst());

        /** @And the page is not the last page */
        self::assertFalse($page->isLast());

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
        /** @Given a page on a middle page of a multi-page result */
        $page = Page::from(
            items: Collection::createFromEmpty(),
            total: 480,
            pagination: Pagination::fromPage(page: 3, perPage: 20)
        );

        /** @When reading the relations of the navigation targets */
        $relations = $page->navigation()->targets()
            ->map(transformations: static fn(NavigationTarget $target): string => $target->relation()->value)
            ->toArray(keyPreservation: KeyPreservation::DISCARD);

        /** @Then it lists the first, previous, next, and last relations in semantic order */
        self::assertSame(['first', 'prev', 'next', 'last'], $relations);
    }

    public function testNavigationWhenMiddlePageGivenThenTheNextTargetPointsAtTheFollowingPage(): void
    {
        /** @Given a page on a middle page of a multi-page result */
        $page = Page::from(
            items: Collection::createFromEmpty(),
            total: 480,
            pagination: Pagination::fromPage(page: 3, perPage: 20)
        );

        /** @When reading the first navigation target */
        $target = $page->navigation()->targets()->first();

        /** @Then the first target is the first page reached through the first relation */
        self::assertEquals(
            NavigationTarget::to(target: Pagination::fromPage(page: 1, perPage: 20), relation: LinkRelation::FIRST),
            $target
        );
    }
}
