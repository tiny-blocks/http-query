<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\CursorPage;
use TinyBlocks\HttpQuery\CursorPagination;
use TinyBlocks\HttpQuery\NavigationTarget;

final class CursorPageTest extends TestCase
{
    public function testNavigationWhenExtraElementFetchedThenListsOnlyTheNextTarget(): void
    {
        /** @Given a keyset page fetched for the page size plus one with an absent incoming cursor */
        $page = CursorPage::from(
            items: Collection::createFrom(elements: [10, 20, 30]),
            keysOf: static fn(mixed $element): array => [$element],
            pagination: CursorPagination::from(cursor: Cursor::none(), perPage: 2)
        );

        /** @When reading the navigation targets */
        $targets = $page->navigation()->targets();

        /** @Then it lists a single navigation target */
        self::assertCount(1, $targets->toArray());

        /** @And the only target is the next target anchored on the forward cursor */
        self::assertEquals(
            NavigationTarget::to(
                target: CursorPagination::from(cursor: Cursor::fromKeys(keys: [20]), perPage: 2),
                relation: LinkRelation::NEXT
            ),
            $targets->first()
        );
    }

    public function testNavigationWhenExtraElementFetchedThenHasNextAndForwardCursorAnchorsLastItem(): void
    {
        /** @Given a keyset pagination with an absent incoming cursor and a page size of two */
        $pagination = CursorPagination::from(cursor: Cursor::none(), perPage: 2);

        /** @And items fetched for the page size plus one */
        $items = Collection::createFrom(elements: [10, 20, 30]);

        /** @When building the cursor page from the items, the key extractor, and the pagination */
        $page = CursorPage::from(
            items: $items,
            keysOf: static fn(mixed $element): array => [$element],
            pagination: $pagination
        );

        /** @Then the page reports a next page */
        self::assertTrue($page->hasNext());

        /** @And the items are trimmed to the page size */
        self::assertSame([10, 20], $page->items()->toArray());

        /** @And the page has no previous page */
        self::assertFalse($page->hasPrevious());

        /** @And the forward cursor anchors on the last retained element */
        self::assertSame([20], $page->nextCursor()->toArray());

        /** @And the forward cursor is present */
        self::assertFalse($page->nextCursor()->isAbsent());

        /** @And the backward cursor is absent */
        self::assertTrue($page->previousCursor()->isAbsent());

        /** @And the metadata carries every navigation flag and cursor in declared key order */
        self::assertSame([
            'has_next'        => true,
            'per_page'        => 2,
            'next_cursor'     => Cursor::fromKeys(keys: [20])->toString(),
            'has_previous'    => false,
            'previous_cursor' => null
        ], $page->metadata());
    }

    public function testNavigationWhenIncomingCursorAndNoExtraElementThenListsOnlyThePreviousTarget(): void
    {
        /** @Given the opaque token produced from ordering key values */
        $token = Cursor::fromKeys(keys: [5])->toString();

        /** @And a keyset page fetched within the page size reached through that incoming cursor */
        $page = CursorPage::from(
            items: Collection::createFrom(elements: [10, 20]),
            keysOf: static fn(mixed $element): array => [$element],
            pagination: CursorPagination::from(cursor: Cursor::from(token: $token), perPage: 2)
        );

        /** @When reading the navigation targets */
        $targets = $page->navigation()->targets();

        /** @Then it lists a single navigation target */
        self::assertCount(1, $targets->toArray());

        /** @And the only target is the previous target anchored on the backward cursor */
        self::assertEquals(
            NavigationTarget::to(
                target: CursorPagination::from(cursor: Cursor::fromKeys(keys: [10]), perPage: 2),
                relation: LinkRelation::PREVIOUS
            ),
            $targets->first()
        );
    }

    public function testNavigationWhenNoExtraElementAndIncomingCursorThenNoNextAndBackwardCursorAnchorsFirstItem(): void
    {
        /** @Given the opaque token produced from ordering key values */
        $existingToken = Cursor::fromKeys(keys: [5])->toString();

        /** @And a keyset pagination with a present incoming cursor and a page size of two */
        $pagination = CursorPagination::from(cursor: Cursor::from(token: $existingToken), perPage: 2);

        /** @And items fetched within the page size */
        $items = Collection::createFrom(elements: [10, 20]);

        /** @When building the cursor page from the items, the key extractor, and the pagination */
        $page = CursorPage::from(
            items: $items,
            keysOf: static fn(mixed $element): array => [$element],
            pagination: $pagination
        );

        /** @Then the page reports no next page */
        self::assertFalse($page->hasNext());

        /** @And the page has a previous page */
        self::assertTrue($page->hasPrevious());

        /** @And the forward cursor is absent */
        self::assertTrue($page->nextCursor()->isAbsent());

        /** @And the backward cursor anchors on the first retained element */
        self::assertSame([10], $page->previousCursor()->toArray());
    }
}
