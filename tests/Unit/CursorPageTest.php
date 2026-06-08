<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\CursorPage;
use TinyBlocks\HttpQuery\CursorPagination;
use TinyBlocks\HttpQuery\NavigationTarget;

final class CursorPageTest extends TestCase
{
    private Criteria $criteria;

    protected function setUp(): void
    {
        $this->criteria = Criteria::fromQuery(request: Query::from(parameters: []));
    }

    public function testToResponseWhenCursorPageGivenThenRendersBodyAndLinkHeader(): void
    {
        /** @Given an opaque token produced from ordering key values */
        $token = Cursor::fromKeys(keys: [5])->toString();

        /** @And query parameters carrying that cursor and a page size of two */
        $query = Query::from(parameters: ['page' => ['cursor' => $token, 'size' => '2']]);

        /** @And the criteria parsed from those parameters */
        $criteria = Criteria::fromQuery(request: $query);

        /** @And a cursor page built from the criteria over items fetched for the page size plus one */
        $page = $criteria->cursorPage(items: [10, 20, 30], keysOf: static fn(mixed $element): array => [$element]);

        /** @When rendering the cursor page as a JSON:API response over the orders base URI */
        $response = $page->toResponse(baseUri: '/v1/orders');

        /** @Then the response body carries the trimmed data, the meta, and the keyset links */
        self::assertSame([
            'data'  => [10, 20],
            'meta'  => [
                'has_next'     => true,
                'per_page'     => 2,
                'has_previous' => true
            ],
            'links' => [
                'self' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', $token),
                'prev' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', Cursor::fromKeys(keys: [10])->toString()),
                'next' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', Cursor::fromKeys(keys: [20])->toString())
            ]
        ], json_decode($response->getBody()->getContents(), true));

        /** @And the Link header folds the self, previous, and next relations */
        self::assertSame(implode(', ', [
            sprintf('</v1/orders?page[cursor]=%s&page[size]=2>; rel="self"', $token),
            sprintf('</v1/orders?page[cursor]=%s&page[size]=2>; rel="prev"', Cursor::fromKeys(keys: [10])->toString()),
            sprintf('</v1/orders?page[cursor]=%s&page[size]=2>; rel="next"', Cursor::fromKeys(keys: [20])->toString())
        ]), $response->getHeaderLine('Link'));
    }

    public function testNavigationWhenExtraElementFetchedThenListsOnlyTheNextTarget(): void
    {
        /** @Given a keyset page fetched for the page size plus one with an absent incoming cursor */
        $page = CursorPage::from(
            items: Collection::createFrom(elements: [10, 20, 30]),
            keysOf: static fn(mixed $element): array => [$element],
            criteria: $this->criteria,
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
            criteria: $this->criteria,
            pagination: $pagination
        );

        /** @Then the page reports a next page */
        self::assertTrue($page->hasNext());

        /** @And the items are trimmed to the page size */
        self::assertSame([10, 20], $page->items()->toArray());

        /** @And the page has no previous page */
        self::assertFalse($page->hasPrevious());

        /** @And the next pagination anchors on the last retained element with the page size */
        self::assertEquals(
            CursorPagination::from(cursor: Cursor::fromKeys(keys: [20]), perPage: 2),
            $page->next()
        );

        /** @And there is no previous pagination */
        self::assertNull($page->previous());

        /** @And the metadata carries every navigation flag in length-ascending key order */
        self::assertSame([
            'has_next'     => true,
            'per_page'     => 2,
            'has_previous' => false
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
            criteria: $this->criteria,
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
            criteria: $this->criteria,
            pagination: $pagination
        );

        /** @Then the page reports no next page */
        self::assertFalse($page->hasNext());

        /** @And the page has a previous page */
        self::assertTrue($page->hasPrevious());

        /** @And there is no next pagination */
        self::assertNull($page->next());

        /** @And the previous pagination anchors on the first retained element with the page size */
        self::assertEquals(
            CursorPagination::from(cursor: Cursor::fromKeys(keys: [10]), perPage: 2),
            $page->previous()
        );

        /** @And the metadata carries every navigation flag in length-ascending key order */
        self::assertSame([
            'has_next'     => false,
            'per_page'     => 2,
            'has_previous' => true
        ], $page->metadata());
    }
}
