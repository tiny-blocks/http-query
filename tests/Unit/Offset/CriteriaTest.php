<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Offset;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\SortFieldNotAllowed;
use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Offset\Page;
use TinyBlocks\HttpQuery\Offset\Slice;
use TinyBlocks\HttpQuery\Operator;
use TinyBlocks\HttpQuery\Schema;
use TinyBlocks\HttpQuery\Sort;

final class CriteriaTest extends TestCase
{
    public function testFromQueryWhenEmptyThenOffsetAndLimitAreDefaults(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the offset starts at zero */
        self::assertSame(0, $criteria->offset());

        /** @And the limit carries the default page size */
        self::assertSame(20, $criteria->limit());
    }

    public function testFromQueryWhenPerPageAtMaximumThenLimitIsAccepted(): void
    {
        /** @Given query parameters carrying a page size at the default maximum */
        $query = Query::from(parameters: ['page' => ['size' => '100']]);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the limit carries the maximum page size */
        self::assertSame(100, $criteria->limit());
    }

    public function testSortWhenClientSortsAllowedFieldsThenReturnsTheClientSort(): void
    {
        /** @Given a schema allowing the client to sort by two fields */
        $schema = Schema::create()->sortable(fields: ['created_at', 'id']);

        /** @When reading the effective sort from a client sort over both fields */
        $sort = Criteria::fromQuery(request: Query::from(parameters: ['sort' => '-created_at,id']), schema: $schema)
            ->sort();

        /** @Then the effective sort is the client sort */
        self::assertEquals(Sort::fromExpression(expression: '-created_at,id'), $sort);
    }

    public function testSortWhenNoClientSortAndDefaultGivenThenReturnsTheDefault(): void
    {
        /** @Given a schema declaring a default sort */
        $schema = Schema::create()->defaultSort(sort: Sort::fromExpression(expression: '-created_at'));

        /** @When reading the effective sort from a query carrying no sort */
        $sort = Criteria::fromQuery(request: Query::from(parameters: []), schema: $schema)->sort();

        /** @Then the effective sort is the schema default */
        self::assertEquals(Sort::fromExpression(expression: '-created_at'), $sort);
    }

    public function testSortWhenNoClientSortAndNoDefaultThenIsEmpty(): void
    {
        /** @Given a schema declaring no sortable field and no default sort */
        $schema = Schema::create();

        /** @When reading the effective sort from a query carrying no sort */
        $sort = Criteria::fromQuery(request: Query::from(parameters: []), schema: $schema)->sort();

        /** @Then the effective sort is empty */
        self::assertTrue($sort->isEmpty());
    }

    public function testSortWhenClientSortsDisallowedFieldThenThrowsSortFieldNotAllowed(): void
    {
        /** @Given a schema allowing the client to sort by a single field */
        $schema = Schema::create()->sortable(fields: ['id']);

        /** @And a query sorting by a field that was never declared sortable */
        $query = Query::from(parameters: ['sort' => 'name']);

        /** @Then an exception indicating the sort field is not allowed is raised */
        $this->expectException(SortFieldNotAllowed::class);
        $this->expectExceptionMessage('Sort field <name> is not allowed.');

        /** @When building the criteria from the query */
        Criteria::fromQuery(request: $query, schema: $schema);
    }

    public function testSortWhenServerControlledAndClientSortsThenThrowsSortFieldNotAllowed(): void
    {
        /** @Given a schema with a server-controlled default and no client-sortable field */
        $schema = Schema::create()->defaultSort(sort: Sort::fromExpression(expression: '-created_at'));

        /** @And a query carrying a client sort */
        $query = Query::from(parameters: ['sort' => 'id']);

        /** @Then an exception indicating the sort field is not allowed is raised */
        $this->expectException(SortFieldNotAllowed::class);

        /** @When building the criteria from the query */
        Criteria::fromQuery(request: $query, schema: $schema);
    }

    public function testPageWhenBuiltFromRequestThenReturnsOffsetPage(): void
    {
        /** @Given a criteria parsed from a request carrying a page size of three */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['size' => '3']]));

        /** @When building an offset page from the total element count and the items */
        $page = $criteria->page(total: 30, items: ['a', 'b', 'c']);

        /** @Then the result is an offset page */
        self::assertInstanceOf(Page::class, $page);

        /** @And it carries the total element count */
        self::assertSame(30, $page->total());
    }

    public function testSliceWhenBuiltFromRequestThenReturnsOffsetSlice(): void
    {
        /** @Given a criteria parsed from a request carrying a page size of three */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['size' => '3']]));

        /** @When building an offset slice from the items fetched for the page size plus one */
        $slice = $criteria->slice(items: ['a', 'b', 'c', 'd']);

        /** @Then the result is an offset slice */
        self::assertInstanceOf(Slice::class, $slice);

        /** @And it reports a next page from the trimmed extra element */
        self::assertTrue($slice->hasNext());
    }

    public function testFromQueryWhenCustomSchemaGivenThenAppliesItsDefaultPageSize(): void
    {
        /** @Given query parameters carrying a page number without a page size */
        $query = Query::from(parameters: ['page' => ['number' => '2']]);

        /** @And a schema lowering the default page size */
        $schema = Schema::create()->defaultPerPage(defaultPerPage: 5);

        /** @When building the criteria from the query and the schema */
        $criteria = Criteria::fromQuery(request: $query, schema: $schema);

        /** @Then the offset is derived from the requested page and the schema default page size */
        self::assertSame(5, $criteria->offset());

        /** @And the limit carries the schema default page size */
        self::assertSame(5, $criteria->limit());
    }

    public function testFromQueryWhenFilterSortAndPageGivenThenEachSpecificationIsValidated(): void
    {
        /** @Given a schema allowing the filtered and sorted fields */
        $schema = Schema::create()
            ->sortable(fields: ['created_at'])
            ->filterable(field: 'status', operators: [Operator::EQUAL]);

        /** @And a query carrying a filter, a sort, a page, and a page size */
        $query = Query::from(parameters: [
            'sort'   => '-created_at',
            'page'   => ['number' => '2', 'size' => '15'],
            'filter' => 'status==paid'
        ]);

        /** @When building the criteria from the query and the schema */
        $criteria = Criteria::fromQuery(request: $query, schema: $schema);

        /** @Then the validated comparisons carry the filtered field and value */
        self::assertEquals(
            [Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL)],
            $criteria->comparisons()
        );

        /** @And the effective sort is the client sort */
        self::assertEquals(Sort::fromExpression(expression: '-created_at'), $criteria->sort());

        /** @And the offset is derived from the requested page and page size */
        self::assertSame(15, $criteria->offset());

        /** @And the limit carries the requested page size */
        self::assertSame(15, $criteria->limit());
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
}
