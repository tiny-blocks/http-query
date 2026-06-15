<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Cursor;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Cursor\Page;
use TinyBlocks\HttpQuery\Cursor\Pagination;
use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\NavigationTarget;
use TinyBlocks\HttpQuery\Sort;

final class PageTest extends TestCase
{
    private Sort $sort;

    private Filter $filter;

    protected function setUp(): void
    {
        $this->sort = Sort::fromExpression(expression: '');
        $this->filter = Group::none();
    }

    public function testNavigationWhenNoExtraElementThenHasNoNextPage(): void
    {
        /** @Given a keyset pagination with an absent incoming cursor and a page size of two */
        $pagination = Pagination::from(cursor: Token::none(), perPage: 2);

        /** @And a cursor page built from items fetched within the page size */
        $page = Page::from(
            sort: $this->sort,
            items: [10, 20],
            filter: $this->filter,
            keysOf: static fn(int $element): array => [$element],
            pagination: $pagination
        );

        /** @Then the page reports no next page */
        self::assertFalse($page->hasNext());

        /** @And there is no next pagination */
        self::assertNull($page->next());

        /** @And the navigation lists no target */
        self::assertCount(0, $page->navigation()->targets()->toArray());

        /** @And the metadata reports no next page in grouped key order (counts and sizes, then the boolean flags) */
        self::assertSame(['per_page' => 2, 'has_next' => false], $page->metadata());
    }

    public function testToResponseWhenFirstCursorPageThenSelfLinkIsCursorStyle(): void
    {
        /** @Given a cursor page on the first page with no incoming cursor */
        $page = Page::from(
            sort: $this->sort,
            items: [10, 20, 30],
            filter: $this->filter,
            keysOf: static fn(int $element): array => [$element],
            pagination: Pagination::from(cursor: Token::none(), perPage: 2)
        );

        /** @When rendering the first cursor page as a JSON:API response over the orders base URI */
        $response = $page->toResponse(baseUri: '/v1/orders');

        /** @Then the self link is cursor-style, carrying only the page size and never an offset page number */
        self::assertSame([
            'data'  => [10, 20],
            'meta'  => [
                'per_page' => 2,
                'has_next' => true
            ],
            'links' => [
                'self' => '/v1/orders?page[size]=2',
                'next' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', Token::fromKeys(keys: [20])->toString())
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testToResponseWhenCursorPageGivenThenRendersBodyAndLinkHeader(): void
    {
        /** @Given an opaque token produced from ordering key values */
        $token = Token::fromKeys(keys: [5])->toString();

        /** @And a cursor page built over items fetched for the page size plus one */
        $page = Page::from(
            sort: $this->sort,
            items: [10, 20, 30],
            filter: $this->filter,
            keysOf: static fn(int $element): array => [$element],
            pagination: Pagination::from(cursor: Token::from(token: $token), perPage: 2)
        );

        /** @When rendering the cursor page as a JSON:API response over the orders base URI */
        $response = $page->toResponse(baseUri: '/v1/orders');

        /** @Then the response body carries the trimmed data, the meta, and the forward-only links */
        self::assertSame([
            'data'  => [10, 20],
            'meta'  => [
                'per_page' => 2,
                'has_next' => true
            ],
            'links' => [
                'self' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', $token),
                'next' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', Token::fromKeys(keys: [20])->toString())
            ]
        ], json_decode($response->getBody()->getContents(), true));

        /** @And the Link header folds the self and next relations */
        self::assertSame(implode(', ', [
            sprintf('</v1/orders?page[cursor]=%s&page[size]=2>; rel="self"', $token),
            sprintf('</v1/orders?page[cursor]=%s&page[size]=2>; rel="next"', Token::fromKeys(keys: [20])->toString())
        ]), $response->getHeaderLine('Link'));
    }

    public function testNavigationWhenExtraElementFetchedThenListsOnlyTheNextTarget(): void
    {
        /** @Given a keyset page fetched for the page size plus one with an absent incoming cursor */
        $page = Page::from(
            sort: $this->sort,
            items: [10, 20, 30],
            filter: $this->filter,
            keysOf: static fn(int $element): array => [$element],
            pagination: Pagination::from(cursor: Token::none(), perPage: 2)
        );

        /** @When reading the navigation targets */
        $targets = $page->navigation()->targets();

        /** @Then it lists a single navigation target */
        self::assertCount(1, $targets->toArray());

        /** @And the only target is the next target anchored on the forward cursor */
        self::assertEquals(
            NavigationTarget::to(
                target: Pagination::from(cursor: Token::fromKeys(keys: [20]), perPage: 2),
                relation: LinkRelation::NEXT
            ),
            $targets->first()
        );
    }

    public function testMapWhenTransformationGivenThenProjectsItemsAndPreservesTheCursor(): void
    {
        /** @Given a cursor page built over items fetched for the page size plus one */
        $page = Page::from(
            sort: $this->sort,
            items: [10, 20, 30],
            filter: $this->filter,
            keysOf: static fn(int $element): array => [$element],
            pagination: Pagination::from(cursor: Token::none(), perPage: 2)
        );

        /** @When mapping the items through a projection */
        $mapped = $page->map(transformation: static fn(int $element): string => sprintf('#%d', $element));

        /** @Then the items are projected through the transformation */
        self::assertSame(['#10', '#20'], $mapped->items()->toArray());

        /** @And the next page hint is preserved */
        self::assertTrue($mapped->hasNext());

        /** @And the next pagination still anchors on the source keys */
        self::assertEquals(Pagination::from(cursor: Token::fromKeys(keys: [20]), perPage: 2), $mapped->next());

        /** @And the metadata is preserved in grouped key order (counts and sizes, then the boolean flags) */
        self::assertSame(['per_page' => 2, 'has_next' => true], $mapped->metadata());
    }

    public function testToResponseWhenPageIsMappedThenRendersProjectedDataAndPreservedLinks(): void
    {
        /** @Given an opaque token produced from ordering key values */
        $token = Token::fromKeys(keys: [5])->toString();

        /** @And a cursor page built over array rows then projected to their identifiers */
        $page = Page::from(
            sort: $this->sort,
            items: [['id' => 10], ['id' => 20], ['id' => 30]],
            filter: $this->filter,
            keysOf: static fn(array $row): array => [$row['id']],
            pagination: Pagination::from(cursor: Token::from(token: $token), perPage: 2)
        )->map(transformation: static fn(array $row): int => (int)$row['id']);

        /** @When rendering the mapped cursor page as a JSON:API response over the orders base URI */
        $response = $page->toResponse(baseUri: '/v1/orders');

        /** @Then the body carries the projected data, the meta, and the forward-only links */
        self::assertSame([
            'data'  => [10, 20],
            'meta'  => [
                'per_page' => 2,
                'has_next' => true
            ],
            'links' => [
                'self' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', $token),
                'next' => sprintf('/v1/orders?page[cursor]=%s&page[size]=2', Token::fromKeys(keys: [20])->toString())
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testNavigationWhenExtraElementFetchedThenHasNextAndNextCursorAnchorsLastItem(): void
    {
        /** @Given a keyset pagination with an absent incoming cursor and a page size of two */
        $pagination = Pagination::from(cursor: Token::none(), perPage: 2);

        /** @And a cursor page built over items fetched for the page size plus one */
        $page = Page::from(
            sort: $this->sort,
            items: [10, 20, 30],
            filter: $this->filter,
            keysOf: static fn(int $element): array => [$element],
            pagination: $pagination
        );

        /** @Then the page reports a next page */
        self::assertTrue($page->hasNext());

        /** @And the items are trimmed to the page size */
        self::assertSame([10, 20], $page->items()->toArray());

        /** @And the next pagination anchors on the last retained element with the page size */
        self::assertEquals(Pagination::from(cursor: Token::fromKeys(keys: [20]), perPage: 2), $page->next());

        /** @And the metadata carries every entry in grouped key order (counts and sizes, then the boolean flags) */
        self::assertSame(['per_page' => 2, 'has_next' => true], $page->metadata());
    }
}
