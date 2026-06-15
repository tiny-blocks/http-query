<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Cursor;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Cursor\Criteria;
use TinyBlocks\HttpQuery\Cursor\Page;
use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\SortIsRequired;
use TinyBlocks\HttpQuery\Operator;
use TinyBlocks\HttpQuery\Schema;
use TinyBlocks\HttpQuery\Sort;

final class CriteriaTest extends TestCase
{
    public function testFromQueryWhenEmptyThenSortIsEmpty(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the effective sort is empty */
        self::assertTrue($criteria->sort()->isEmpty());
    }

    public function testFromQueryWhenEmptyThenComparisonsAreEmpty(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then there is no comparison */
        self::assertSame([], $criteria->comparisons());
    }

    public function testKeysetWhenBuiltWithoutCursorThenBuildsCursorPage(): void
    {
        /** @Given a schema declaring a default sort over the identifier */
        $schema = Schema::create()->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @And a criteria parsed from a request carrying a page size of two and no cursor */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['size' => '2']]), schema: $schema);

        /** @When building a cursor page through the keyset view over the array rows fetched */
        $page = $criteria->keyset()->page(items: [['id' => 10], ['id' => 20], ['id' => 30]]);

        /** @Then the result is a cursor page */
        self::assertInstanceOf(Page::class, $page);

        /** @And the items are trimmed to the page size */
        self::assertSame([['id' => 10], ['id' => 20]], $page->items()->toArray());
    }

    public function testKeysetWhenIncomingCursorPresentThenBuildsCursorPage(): void
    {
        /** @Given an opaque token produced from ordering key values */
        $token = Token::fromKeys(keys: [5])->toString();

        /** @And a schema declaring a default sort over the identifier */
        $schema = Schema::create()->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @And a criteria parsed from a request carrying that cursor and a page size of two */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['cursor' => $token, 'size' => '2']]),
            schema: $schema
        );

        /** @When building a cursor page through the keyset view over the items fetched */
        $page = $criteria->keyset()->page(items: [10, 20, 30], keysOf: static fn(int $element): array => [$element]);

        /** @Then the cursor page reports a next page */
        self::assertTrue($page->hasNext());

        /** @And the items are trimmed to the page size */
        self::assertSame([10, 20], $page->items()->toArray());
    }

    public function testKeysetWhenEffectiveSortIsEmptyThenThrowsSortIsRequired(): void
    {
        /** @Given a criteria parsed from a request carrying no sort and no schema default */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: []));

        /** @Then an exception indicating a deterministic order is required is raised */
        $this->expectException(SortIsRequired::class);
        $this->expectExceptionMessage('A keyset requires a deterministic order, but the effective sort is empty.');

        /** @When building the keyset view */
        $criteria->keyset();
    }

    public function testFromQueryWhenCustomSchemaGivenThenAppliesItsDefaultPageSize(): void
    {
        /** @Given a schema lowering the default page size and declaring a default sort */
        $schema = Schema::create()
            ->defaultPerPage(defaultPerPage: 5)
            ->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @When building the keyset view from a query carrying no page size and the schema */
        $keyset = Criteria::fromQuery(request: Query::from(parameters: []), schema: $schema)->keyset();

        /** @Then the keyset carries the schema default page size */
        self::assertSame(5, $keyset->limit());
    }

    public function testFromQueryWhenCursorPresentThenKeysetCarriesPageSizeAndCursor(): void
    {
        /** @Given an opaque token produced from a single ordering key value */
        $token = Token::fromKeys(keys: [5])->toString();

        /** @And a schema declaring a default sort over the identifier */
        $schema = Schema::create()->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @And a criteria parsed from a request carrying that cursor and a page size of ten */
        $criteria = Criteria::fromQuery(
            request: Query::from(parameters: ['page' => ['cursor' => $token, 'size' => '10']]),
            schema: $schema
        );

        /** @When building the keyset view */
        $keyset = $criteria->keyset();

        /** @Then the keyset carries the requested page size */
        self::assertSame(10, $keyset->limit());

        /** @And the keyset decodes the incoming cursor keyed by the sort field */
        self::assertSame(['id' => 5], $keyset->cursor());
    }

    public function testFromQueryWhenPerPageAboveMaximumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given query parameters carrying a page size above the default maximum */
        $query = Query::from(parameters: ['page' => ['size' => '500']]);

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size');

        /** @When building the criteria from the query */
        Criteria::fromQuery(request: $query);
    }

    public function testFromQueryWhenFilterAndSortGivenThenEachSpecificationIsValidated(): void
    {
        /** @Given a schema allowing the filtered and sorted fields */
        $schema = Schema::create()
            ->sortable(fields: ['created_at'])
            ->filterable(field: 'status', operators: [Operator::EQUAL]);

        /** @And a query carrying a filter and a sort */
        $query = Query::from(parameters: ['sort' => '-created_at', 'filter' => 'status==paid']);

        /** @When building the criteria from the query and the schema */
        $criteria = Criteria::fromQuery(request: $query, schema: $schema);

        /** @Then the validated comparisons carry the filtered field and value */
        self::assertEquals(
            [Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL)],
            $criteria->comparisons()
        );

        /** @And the effective sort is the client sort */
        self::assertEquals(Sort::fromExpression(expression: '-created_at'), $criteria->sort());
    }
}
