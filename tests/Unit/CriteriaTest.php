<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\CursorPagination;
use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\Pagination;
use TinyBlocks\HttpQuery\Schema;

final class CriteriaTest extends TestCase
{
    public function testFromQueryWhenEmptyThenSortingIsEmpty(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(query: $query);

        /** @Then the sorting is empty */
        self::assertTrue($criteria->sorting()->isEmpty());
    }

    public function testFromQueryWhenEmptyThenFilteringIsAnEmptyGroup(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(query: $query);

        /** @Then the filtering is an empty group */
        self::assertInstanceOf(Group::class, $criteria->filtering());

        /** @And the group carries no child filter */
        self::assertSame([], $criteria->filtering()->filters());
    }

    public function testFromQueryWhenNoCursorThenPaginationIsOffsetBased(): void
    {
        /** @Given query parameters without a cursor */
        $query = Query::from(parameters: ['page' => '2', 'per_page' => '15']);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(query: $query);

        /** @Then the pagination is offset-based */
        self::assertInstanceOf(Pagination::class, $criteria->pagination());
    }

    public function testToUriWhenAndGroupNestedInOrThenLeavesItUnwrapped(): void
    {
        /** @Given a criteria whose filter nests an AND group inside an OR group */
        $criteria = Criteria::fromQuery(query: Query::from(parameters: ['filter' => 'a==1;b==2,c==3']));

        /** @When serializing the criteria back to a URI */
        $actual = $criteria->toUri(baseUri: '/v1/orders');

        /** @Then the tighter-binding AND group is left unwrapped */
        self::assertSame('/v1/orders?filter=a==1;b==2,c==3&page=1&per_page=20', $actual);
    }

    public function testFromQueryWhenPerPageIsAtTheMaximumThenItIsAccepted(): void
    {
        /** @Given query parameters carrying a page size at the default maximum */
        $query = Query::from(parameters: ['per_page' => '100']);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(query: $query);

        /** @Then the pagination is offset-based */
        self::assertInstanceOf(Pagination::class, $criteria->pagination());

        /** @And it carries the maximum page size */
        self::assertSame(100, $criteria->pagination()->limit());
    }

    public function testToUriWhenOrGroupNestedInAndThenWrapsItInParentheses(): void
    {
        /** @Given a criteria whose filter nests an OR group inside an AND group */
        $criteria = Criteria::fromQuery(query: Query::from(parameters: ['filter' => '(a==1,b==2);c==3']));

        /** @When serializing the criteria back to a URI */
        $actual = $criteria->toUri(baseUri: '/v1/orders');

        /** @Then the nested OR group is wrapped in parentheses */
        self::assertSame('/v1/orders?filter=(a==1,b==2);c==3&page=1&per_page=20', $actual);
    }

    public function testToUriWhenValueCarriesReservedCharactersThenItIsQuoted(): void
    {
        /** @Given a criteria whose filter value carries a space */
        $criteria = Criteria::fromQuery(query: Query::from(parameters: ['filter' => 'name=="John Doe"']));

        /** @When serializing the criteria back to a URI */
        $actual = $criteria->toUri(baseUri: '/v1/orders');

        /** @Then the value is rendered with surrounding double quotes */
        self::assertSame('/v1/orders?filter=name=="John Doe"&page=1&per_page=20', $actual);
    }

    public function testFromQueryWhenCursorIsPresentThenPaginationIsCursorBased(): void
    {
        /** @Given query parameters carrying a cursor */
        $query = Query::from(parameters: ['cursor' => 'abc', 'per_page' => '10']);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(query: $query);

        /** @Then the pagination is cursor-based */
        self::assertInstanceOf(CursorPagination::class, $criteria->pagination());

        /** @And it carries the requested page size */
        self::assertSame(10, $criteria->pagination()->limit());

        /** @And it carries the incoming cursor token */
        self::assertSame('abc', $criteria->pagination()->cursor()->toString());
    }

    public function testToUriWhenValueCarriesQuoteAndBackslashThenBothAreEscaped(): void
    {
        /** @Given a criteria whose filter value carries a double quote and a backslash */
        $criteria = Criteria::fromQuery(query: Query::from(parameters: ['filter' => 'name=="a\"b\\\\c"']));

        /** @When serializing the criteria back to a URI */
        $actual = $criteria->toUri(baseUri: '/v1/orders');

        /** @Then the quote and the backslash are escaped within the double-quoted value */
        self::assertSame('/v1/orders?filter=name=="a\"b\\\\c"&page=1&per_page=20', $actual);
    }

    public function testFromQueryWhenEmptyThenPaginationCarriesDefaultPageAndLimit(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(query: $query);

        /** @Then the pagination is offset-based */
        self::assertInstanceOf(Pagination::class, $criteria->pagination());

        /** @And it starts at the first page */
        self::assertSame(1, $criteria->pagination()->page());

        /** @And it carries the default page size */
        self::assertSame(20, $criteria->pagination()->limit());
    }

    public function testWithPaginationWhenPageReplacedThenFilterAndSortArePreserved(): void
    {
        /** @Given a criteria parsed from a filter, a sort, a page, and a page size */
        $criteria = Criteria::fromQuery(query: Query::from(parameters: [
            'sort'     => '-created_at,id',
            'page'     => '3',
            'filter'   => 'status==paid',
            'per_page' => '20'
        ]));

        /** @And a replacement pagination pointing at a new page */
        $pagination = Pagination::fromPage(page: 5, perPage: 20);

        /** @When replacing the pagination and serializing back to a URI */
        $actual = $criteria->withPagination(pagination: $pagination)->toUri(baseUri: '/v1/orders');

        /** @Then the URI keeps the filter and the sort while pointing at the new page */
        self::assertSame('/v1/orders?filter=status==paid&sort=-created_at,id&page=5&per_page=20', $actual);
    }

    public function testFromQueryWhenCustomSchemaGivenThenParsesAgainstTheCustomKeys(): void
    {
        /** @Given query parameters using custom key names */
        $query = Query::from(parameters: ['q' => 'name==bob', 'size' => '5', 'p' => '2']);

        /** @And a schema mapping those custom key names */
        $schema = Schema::default()
            ->withPageKey(pageKey: 'p')
            ->withFilterKey(filterKey: 'q')
            ->withPerPageKey(perPageKey: 'size');

        /** @When building the criteria from the query and the schema */
        $criteria = Criteria::fromQuery(query: $query, schema: $schema);

        /** @Then the pagination is offset-based */
        self::assertInstanceOf(Pagination::class, $criteria->pagination());

        /** @And the pagination points at the requested page */
        self::assertSame(2, $criteria->pagination()->page());

        /** @And the pagination carries the requested page size */
        self::assertSame(5, $criteria->pagination()->limit());

        /** @And the filtering is a comparison */
        self::assertInstanceOf(Comparison::class, $criteria->filtering());

        /** @And the comparison targets the filtered field */
        self::assertSame('name', $criteria->filtering()->field());
    }

    public function testFromQueryWhenPerPageAboveMaximumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given query parameters carrying a page size above the default maximum */
        $query = Query::from(parameters: ['per_page' => '500']);

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size');

        /** @When building the criteria from the query */
        Criteria::fromQuery(query: $query);
    }

    public function testToUriWhenFilterSortAndPageGivenThenRoundTripsToTheCanonicalUri(): void
    {
        /** @Given a criteria parsed from a filter, a sort, a page, and a page size */
        $criteria = Criteria::fromQuery(query: Query::from(parameters: [
            'sort'     => '-created_at,id',
            'page'     => '3',
            'filter'   => 'status==paid;total=ge=100',
            'per_page' => '20'
        ]));

        /** @When serializing the criteria back to a URI */
        $actual = $criteria->toUri(baseUri: '/v1/orders');

        /** @Then the URI preserves the filter, the sort, and the pagination */
        self::assertSame('/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=3&per_page=20', $actual);
    }

    public function testFromQueryWhenFilterSortAndPageGivenThenEachSpecificationIsParsed(): void
    {
        /** @Given query parameters carrying a filter, a sort, a page, and a page size */
        $query = Query::from(parameters: [
            'sort'     => '-created_at',
            'page'     => '2',
            'filter'   => 'status==paid',
            'per_page' => '15'
        ]);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQuery(query: $query);

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
        self::assertInstanceOf(Pagination::class, $criteria->pagination());

        /** @And it points at the requested page */
        self::assertSame(2, $criteria->pagination()->page());

        /** @And it carries the requested page size */
        self::assertSame(15, $criteria->pagination()->limit());
    }
}
