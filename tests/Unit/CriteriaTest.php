<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\CursorPage;
use TinyBlocks\HttpQuery\CursorPagination;
use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\OffsetPage;
use TinyBlocks\HttpQuery\OffsetPagination;
use TinyBlocks\HttpQuery\OffsetSlice;
use TinyBlocks\HttpQuery\Schema;

final class CriteriaTest extends TestCase
{
    public function testFromQueryWhenEmptyThenSortingIsEmpty(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the sorting is empty */
        self::assertTrue($criteria->sorting()->isEmpty());
    }

    public function testFromQueryWhenEmptyThenFilteringIsAnEmptyGroup(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the filtering is an empty group */
        self::assertInstanceOf(Group::class, $criteria->filtering());

        /** @And the group carries no child filter */
        self::assertSame([], $criteria->filtering()->filters());
    }

    public function testFromQueryWhenNoCursorThenPaginationIsOffsetBased(): void
    {
        /** @Given query parameters without a cursor */
        $query = Query::from(parameters: ['page' => ['number' => '2', 'size' => '15']]);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the pagination is offset-based */
        self::assertInstanceOf(OffsetPagination::class, $criteria->pagination());
    }

    public function testFromQueryWhenPerPageIsAtTheMaximumThenItIsAccepted(): void
    {
        /** @Given query parameters carrying a page size at the default maximum */
        $query = Query::from(parameters: ['page' => ['size' => '100']]);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the pagination is offset-based */
        self::assertInstanceOf(OffsetPagination::class, $criteria->pagination());

        /** @And it carries the maximum page size */
        self::assertSame(100, $criteria->pagination()->limit());
    }

    public function testCursorPageWhenBuiltFromRequestThenReturnsCursorPage(): void
    {
        /** @Given a criteria parsed from a request carrying a page size of two */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['size' => '2']]));

        /** @When building a cursor page from the items fetched for the page size plus one */
        $page = $criteria->cursorPage(items: [10, 20, 30], keysOf: static fn(mixed $element): array => [$element]);

        /** @Then the result is a cursor page */
        self::assertInstanceOf(CursorPage::class, $page);

        /** @And the items are trimmed to the page size */
        self::assertSame([10, 20], $page->items()->toArray());
    }

    public function testOffsetPageWhenBuiltFromRequestThenReturnsOffsetPage(): void
    {
        /** @Given a criteria parsed from a request carrying a page size of three */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['size' => '3']]));

        /** @When building an offset page from the items and the total element count */
        $page = $criteria->offsetPage(items: ['a', 'b', 'c'], total: 30);

        /** @Then the result is an offset page */
        self::assertInstanceOf(OffsetPage::class, $page);

        /** @And it carries the total element count */
        self::assertSame(30, $page->total());
    }

    public function testOffsetSliceWhenBuiltFromRequestThenReturnsOffsetSlice(): void
    {
        /** @Given a criteria parsed from a request carrying a page size of three */
        $criteria = Criteria::fromQuery(request: Query::from(parameters: ['page' => ['size' => '3']]));

        /** @When building an offset slice from the items fetched for the page size plus one */
        $slice = $criteria->offsetSlice(items: ['a', 'b', 'c', 'd']);

        /** @Then the result is an offset slice */
        self::assertInstanceOf(OffsetSlice::class, $slice);

        /** @And it reports a next page from the trimmed extra element */
        self::assertTrue($slice->hasNext());
    }

    public function testFromQueryWhenCursorIsPresentThenPaginationIsCursorBased(): void
    {
        /** @Given query parameters carrying a cursor */
        $query = Query::from(parameters: ['page' => ['cursor' => 'abc', 'size' => '10']]);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the pagination is cursor-based */
        self::assertInstanceOf(CursorPagination::class, $criteria->pagination());

        /** @And it carries the requested page size */
        self::assertSame(10, $criteria->pagination()->limit());

        /** @And it carries the incoming cursor token */
        self::assertSame('abc', $criteria->pagination()->cursor()->toString());
    }

    public function testFromQueryWhenEmptyThenPaginationCarriesDefaultPageAndLimit(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the pagination is offset-based */
        self::assertInstanceOf(OffsetPagination::class, $criteria->pagination());

        /** @And it starts at the first page */
        self::assertSame(1, $criteria->pagination()->page());

        /** @And it carries the default page size */
        self::assertSame(20, $criteria->pagination()->limit());
    }

    public function testFromQueryWhenCustomSchemaGivenThenAppliesItsDefaultPageSize(): void
    {
        /** @Given query parameters carrying a page number without a page size */
        $query = Query::from(parameters: ['page' => ['number' => '2']]);

        /** @And a schema lowering the default page size */
        $schema = Schema::default()->withDefaultPerPage(defaultPerPage: 5);

        /** @When building the criteria from the query and the schema */
        $criteria = Criteria::fromQuery(request: $query, schema: $schema);

        /** @Then the pagination is offset-based */
        self::assertInstanceOf(OffsetPagination::class, $criteria->pagination());

        /** @And the pagination points at the requested page */
        self::assertSame(2, $criteria->pagination()->page());

        /** @And the pagination carries the schema default page size */
        self::assertSame(5, $criteria->pagination()->limit());
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

    public function testFromQueryWhenFilterSortAndPageGivenThenEachSpecificationIsParsed(): void
    {
        /** @Given query parameters carrying a filter, a sort, a page, and a page size */
        $query = Query::from(parameters: [
            'sort'   => '-created_at',
            'page'   => ['number' => '2', 'size' => '15'],
            'filter' => 'status==paid'
        ]);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(request: $query);

        /** @Then the filtering is a comparison */
        self::assertInstanceOf(Comparison::class, $criteria->filtering());

        /** @And the comparison targets the filtered field */
        self::assertSame('status', $criteria->filtering()->field());

        /** @And the sorting carries exactly one order */
        self::assertCount(1, $criteria->sorting()->orders());

        /** @And the single order sorts by the descending field */
        self::assertSame('created_at', $criteria->sorting()->orders()[0]->field());

        /** @And the single order applies descending direction */
        self::assertSame(Direction::DESCENDING, $criteria->sorting()->orders()[0]->direction());

        /** @And the pagination is offset-based */
        self::assertInstanceOf(OffsetPagination::class, $criteria->pagination());

        /** @And it points at the requested page */
        self::assertSame(2, $criteria->pagination()->page());

        /** @And it carries the requested page size */
        self::assertSame(15, $criteria->pagination()->limit());
    }
}
