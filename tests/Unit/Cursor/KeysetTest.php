<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Cursor;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Cursor\Criteria;
use TinyBlocks\HttpQuery\Cursor\Pagination;
use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;
use TinyBlocks\HttpQuery\Order;
use TinyBlocks\HttpQuery\Schema;

final class KeysetTest extends TestCase
{
    private Schema $schema;

    protected function setUp(): void
    {
        $this->schema = Schema::create()->sortable(fields: ['id', 'name', 'created_at']);
    }

    public function testLimitThenReturnsThePageSize(): void
    {
        /** @Given a keyset view built from a request carrying a sort and a page size of fifteen */
        $keyset = Criteria::fromQuery(
            request: Query::from(parameters: ['sort' => 'id', 'page' => ['size' => '15']]),
            schema: $this->schema
        )->keyset();

        /** @When reading the page size */
        $limit = $keyset->limit();

        /** @Then it returns the requested page size */
        self::assertSame(15, $limit);
    }

    public function testOrdersWhenSortGivenThenReturnsItsOrders(): void
    {
        /** @Given a keyset view built from a descending sort over a single field */
        $keyset = Criteria::fromQuery(request: Query::from(parameters: ['sort' => '-name']), schema: $this->schema)
            ->keyset();

        /** @When reading the orders */
        $orders = $keyset->orders();

        /** @Then it returns the orders of the effective sort */
        self::assertEquals([Order::from(field: 'name', direction: Direction::DESCENDING)], $orders);
    }

    public function testPageWhenKeysOfGivenThenUsesTheExtractor(): void
    {
        /** @Given a keyset view built from a request carrying a sort and a page size of two */
        $keyset = Criteria::fromQuery(
            request: Query::from(parameters: ['sort' => 'id', 'page' => ['size' => '2']]),
            schema: $this->schema
        )->keyset();

        /** @When building the page from the items and an explicit key extractor */
        $page = $keyset->page(items: [10, 20, 30], keysOf: static fn(int $element): array => [$element]);

        /** @Then the items are trimmed to the page size */
        self::assertSame([10, 20], $page->items()->toArray());

        /** @And the next pagination anchors on the keys extracted from the last retained element */
        self::assertEquals(Pagination::from(perPage: 2, cursor: Token::fromKeys(keys: [20])), $page->next());
    }

    public function testPageWhenNoKeysOfGivenThenDerivesKeysFromTheSortFields(): void
    {
        /** @Given a keyset view built from a request carrying a sort and a page size of two */
        $keyset = Criteria::fromQuery(
            request: Query::from(parameters: ['sort' => 'id', 'page' => ['size' => '2']]),
            schema: $this->schema
        )->keyset();

        /** @When building the page from array rows without a key extractor */
        $page = $keyset->page(items: [['id' => 10], ['id' => 20], ['id' => 30]]);

        /** @Then the items are trimmed to the page size */
        self::assertSame([['id' => 10], ['id' => 20]], $page->items()->toArray());

        /** @And the next pagination anchors on the sort-field keys of the last retained row */
        self::assertEquals(Pagination::from(perPage: 2, cursor: Token::fromKeys(keys: [20])), $page->next());
    }

    public function testCursorWhenNoIncomingCursorThenEverySortFieldIsNull(): void
    {
        /** @Given a keyset view built from a request carrying a sort over two fields */
        $keyset = Criteria::fromQuery(
            request: Query::from(parameters: ['sort' => 'created_at,id', 'page' => ['size' => '2']]),
            schema: $this->schema
        )->keyset();

        /** @When reading the incoming cursor key values */
        $cursor = $keyset->cursor();

        /** @Then every sort field is present with a null value */
        self::assertSame(['created_at' => null, 'id' => null], $cursor);
    }

    public function testCursorWhenIncomingCursorGivenThenKeysValuesBySortField(): void
    {
        /** @Given an opaque token produced from the ordering key values */
        $token = Token::fromKeys(keys: ['2023-01-15T10:30:00Z', 5])->toString();

        /** @And a keyset view built from a request carrying that cursor and a sort over two fields */
        $keyset = Criteria::fromQuery(
            request: Query::from(
                parameters: ['sort' => 'created_at,id', 'page' => ['cursor' => $token, 'size' => '2']]
            ),
            schema: $this->schema
        )->keyset();

        /** @When reading the incoming cursor key values */
        $cursor = $keyset->cursor();

        /** @Then the values are keyed by the sort field names */
        self::assertSame(['created_at' => '2023-01-15T10:30:00Z', 'id' => 5], $cursor);
    }

    public function testCursorWhenDecodedCountMismatchesThenThrowsCursorIsInvalid(): void
    {
        /** @Given an opaque token carrying a single key value */
        $token = Token::fromKeys(keys: [5])->toString();

        /** @And a keyset view whose sort carries two fields */
        $keyset = Criteria::fromQuery(
            request: Query::from(
                parameters: ['sort' => 'created_at,id', 'page' => ['cursor' => $token, 'size' => '2']]
            ),
            schema: $this->schema
        )->keyset();

        /** @Then an exception indicating the cursor is invalid is raised */
        $this->expectException(CursorIsInvalid::class);

        /** @When reading the incoming cursor key values */
        $keyset->cursor();
    }
}
