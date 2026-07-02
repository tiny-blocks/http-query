<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\Internal\Rsql\Renderer;
use TinyBlocks\HttpQuery\Internal\Uri;
use TinyBlocks\HttpQuery\Links;
use TinyBlocks\HttpQuery\LogicalOperator;
use TinyBlocks\HttpQuery\Navigation;
use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Offset\Pagination;
use TinyBlocks\HttpQuery\Operator;
use TinyBlocks\HttpQuery\Sort;

final class LinksTest extends TestCase
{
    public function testToArrayWhenReservedCharacterInValueThenQuotesIt(): void
    {
        /** @Given a comparison whose value carries a space */
        $filter = Comparison::of(field: 'name', values: ['John Doe'], operator: Operator::EQUAL);

        /** @When rendering the navigation for a page carrying that filter */
        $links = Links::from(
            self: Pagination::fromPage(page: 1, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: $filter,
            baseUri: '/v1/orders',
            navigation: Navigation::empty()
        );

        /** @Then the self link renders the value within double quotes */
        self::assertSame(
            '/v1/orders?filter=name==%22John%20Doe%22&page[number]=1&page[size]=20',
            $links->toArray()['self']
        );
    }

    public function testToArrayWhenPageIsTheLastThenOmitsTheNextRelation(): void
    {
        /** @Given a criteria pointing at the twenty-fourth page */
        $criteria = Criteria::fromQueryWithDefaultSchema(
            request: Query::from(parameters: ['page' => ['number' => '24', 'size' => '20']])
        );

        /** @And a page carrying a total spanning twenty-four pages */
        $result = $criteria->page(items: range(1, 20), total: 480);

        /** @When rendering the navigation for the last page from its own pagination */
        $links = Links::from(
            self: Pagination::fromPage(page: 24, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: Group::none(),
            baseUri: '/v1/orders',
            navigation: $result->navigation()
        );

        /** @Then the navigation exposes the self, first, previous, and last relations in that order */
        self::assertSame([
            'self'  => '/v1/orders?page[number]=24&page[size]=20',
            'first' => '/v1/orders?page[number]=1&page[size]=20',
            'prev'  => '/v1/orders?page[number]=23&page[size]=20',
            'last'  => '/v1/orders?page[number]=24&page[size]=20'
        ], $links->toArray());

        /** @And the navigation carries no next relation */
        self::assertArrayNotHasKey('next', $links->toArray());
    }

    public function testToArrayWhenAndGroupNestedInOrThenLeavesItUnwrapped(): void
    {
        /** @Given an OR group whose first child is an AND group */
        $filter = Group::of(filters: [
            Group::of(filters: [
                Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
            ], operator: LogicalOperator::AND),
            Comparison::of(field: 'c', values: ['3'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::OR);

        /** @When rendering the navigation for a page carrying that filter */
        $links = Links::from(
            self: Pagination::fromPage(page: 1, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: $filter,
            baseUri: '/v1/orders',
            navigation: Navigation::empty()
        );

        /** @Then the self link leaves the tighter-binding AND group unwrapped */
        self::assertSame(
            '/v1/orders?filter=a==1;b==2,c==3&page[number]=1&page[size]=20',
            $links->toArray()['self']
        );
    }

    public function testToArrayWhenQuoteAndBackslashInValueThenEscapesBoth(): void
    {
        /** @Given a comparison whose value carries a double quote and a backslash */
        $filter = Comparison::of(field: 'name', values: ['a"b\\c'], operator: Operator::EQUAL);

        /** @When rendering the navigation for a page carrying that filter */
        $links = Links::from(
            self: Pagination::fromPage(page: 1, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: $filter,
            baseUri: '/v1/orders',
            navigation: Navigation::empty()
        );

        /** @Then the self link escapes the quote and the backslash within the double-quoted value */
        self::assertSame(
            '/v1/orders?filter=name==%22a%5C%22b%5C%5Cc%22&page[number]=1&page[size]=20',
            $links->toArray()['self']
        );
    }

    public function testToArrayWhenInListFilterThenRendersParenthesizedValues(): void
    {
        /** @Given an IN comparison carrying several values */
        $filter = Comparison::of(field: 'role', values: ['admin', 'user'], operator: Operator::IN);

        /** @When rendering the navigation for a page carrying that filter */
        $links = Links::from(
            self: Pagination::fromPage(page: 1, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: $filter,
            baseUri: '/v1/orders',
            navigation: Navigation::empty()
        );

        /** @Then the self link wraps the comma-joined values in parentheses */
        self::assertSame(
            '/v1/orders?filter=role=in=(admin,user)&page[number]=1&page[size]=20',
            $links->toArray()['self']
        );
    }

    public function testToArrayWhenOrGroupNestedInAndThenWrapsItInParentheses(): void
    {
        /** @Given an AND group whose first child is an OR group */
        $filter = Group::of(filters: [
            Group::of(filters: [
                Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
            ], operator: LogicalOperator::OR),
            Comparison::of(field: 'c', values: ['3'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND);

        /** @When rendering the navigation for a page carrying that filter */
        $links = Links::from(
            self: Pagination::fromPage(page: 1, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: $filter,
            baseUri: '/v1/orders',
            navigation: Navigation::empty()
        );

        /** @Then the self link wraps the tighter-binding OR group in parentheses */
        self::assertSame(
            '/v1/orders?filter=(a==1,b==2);c==3&page[number]=1&page[size]=20',
            $links->toArray()['self']
        );
    }

    public function testToArrayWhenPageIsTheFirstThenOmitsThePreviousRelation(): void
    {
        /** @Given a criteria pointing at the first page */
        $criteria = Criteria::fromQueryWithDefaultSchema(
            request: Query::from(parameters: ['page' => ['number' => '1', 'size' => '20']])
        );

        /** @And a page carrying a total spanning twenty-four pages */
        $result = $criteria->page(items: range(1, 20), total: 480);

        /** @When rendering the navigation for the first page from its own pagination */
        $links = Links::from(
            self: Pagination::fromPage(page: 1, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: Group::none(),
            baseUri: '/v1/orders',
            navigation: $result->navigation()
        );

        /** @Then the navigation exposes the self, first, next, and last relations in that order */
        self::assertSame([
            'self'  => '/v1/orders?page[number]=1&page[size]=20',
            'first' => '/v1/orders?page[number]=1&page[size]=20',
            'next'  => '/v1/orders?page[number]=2&page[size]=20',
            'last'  => '/v1/orders?page[number]=24&page[size]=20'
        ], $links->toArray());

        /** @And the navigation carries no previous relation */
        self::assertArrayNotHasKey('prev', $links->toArray());
    }

    public function testToArrayWhenSliceMiddleThenExposesSelfFirstPrevAndNext(): void
    {
        /** @Given a criteria at a middle page */
        $criteria = Criteria::fromQueryWithDefaultSchema(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @And a slice fetched for the page size plus one so a next page exists */
        $result = $criteria->slice(items: range(1, 21));

        /** @When rendering the navigation preserving a filter and a sort */
        $links = Links::from(
            self: Pagination::fromPage(page: 3, perPage: 20),
            sort: Sort::fromExpression(expression: '-created_at,id'),
            filter: Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL),
            baseUri: '/v1/orders',
            navigation: $result->navigation()
        );

        /** @Then the navigation exposes the self, first, previous, and next relations preserving filter and sort */
        self::assertSame([
            'self'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=3&page[size]=20',
            'first' => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=1&page[size]=20',
            'prev'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=2&page[size]=20',
            'next'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=4&page[size]=20'
        ], $links->toArray());
    }

    public function testToArrayWhenOrGroupFilterThenJoinsChildrenWithTheOrToken(): void
    {
        /** @Given an OR group of two comparisons */
        $filter = Group::of(filters: [
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
            Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::OR);

        /** @When rendering the navigation for a page carrying that filter */
        $links = Links::from(
            self: Pagination::fromPage(page: 1, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: $filter,
            baseUri: '/v1/orders',
            navigation: Navigation::empty()
        );

        /** @Then the self link joins the children with the OR token */
        self::assertSame(
            '/v1/orders?filter=a==1,b==2&page[number]=1&page[size]=20',
            $links->toArray()['self']
        );
    }

    public function testToArrayWhenSliceMiddleThenCarriesAFirstButNoLastRelation(): void
    {
        /** @Given a criteria pointing at a middle page */
        $criteria = Criteria::fromQueryWithDefaultSchema(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @And a slice fetched for the page size plus one so a next page exists */
        $result = $criteria->slice(items: range(1, 21));

        /** @When rendering the navigation for the slice from its own pagination */
        $links = Links::from(
            self: Pagination::fromPage(page: 3, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: Group::none(),
            baseUri: '/v1/orders',
            navigation: $result->navigation()
        );

        /** @Then the navigation carries a first relation */
        self::assertArrayHasKey('first', $links->toArray());

        /** @And the navigation carries no last relation */
        self::assertArrayNotHasKey('last', $links->toArray());
    }

    public function testToArrayWhenAndGroupFilterThenJoinsChildrenWithTheAndToken(): void
    {
        /** @Given an AND group of two comparisons */
        $filter = Group::of(filters: [
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
            Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND);

        /** @When rendering the navigation for a page carrying that filter */
        $links = Links::from(
            self: Pagination::fromPage(page: 1, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: $filter,
            baseUri: '/v1/orders',
            navigation: Navigation::empty()
        );

        /** @Then the self link joins the children with the AND token */
        self::assertSame(
            '/v1/orders?filter=a==1;b==2&page[number]=1&page[size]=20',
            $links->toArray()['self']
        );
    }

    public function testConstructorWhenInvokedThroughReflectionThenInstantiatesTheStaticOnlyUri(): void
    {
        /** @Given an uninitialized instance of the static-only URI assembler */
        $uri = new ReflectionClass(Uri::class)->newInstanceWithoutConstructor();

        /** @When invoking its otherwise-uncallable private constructor */
        new ReflectionMethod(Uri::class, '__construct')->invoke($uri);

        /** @Then the static-only URI assembler is instantiated */
        self::assertInstanceOf(Uri::class, $uri);
    }

    public function testToHeaderWhenPageInTheMiddleThenFoldsEveryRelationIntoOneCommaJoinedValue(): void
    {
        /** @Given a criteria at a middle page */
        $criteria = Criteria::fromQueryWithDefaultSchema(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @And a page carrying a total spanning twenty-four pages */
        $result = $criteria->page(items: range(1, 20), total: 480);

        /** @When rendering the navigation as an RFC 8288 Link header preserving a filter and a sort */
        $header = Links::from(
            self: Pagination::fromPage(page: 3, perPage: 20),
            sort: Sort::fromExpression(expression: '-created_at,id'),
            filter: Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL),
            baseUri: '/v1/orders',
            navigation: $result->navigation()
        )->toHeader();

        /** @Then every present relation is folded into one comma-joined Link header value */
        self::assertSame(['Link' => [implode(', ', [
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=3&page[size]=20>; rel="self"',
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=1&page[size]=20>; rel="first"',
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=2&page[size]=20>; rel="prev"',
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=4&page[size]=20>; rel="next"',
            '</v1/orders?filter=status==paid&sort=-created_at,id&page[number]=24&page[size]=20>; rel="last"'
        ])]], $header->toArray());
    }

    public function testToArrayWhenPageInTheMiddleThenExposesEveryRelationPreservingFilterAndSort(): void
    {
        /** @Given a criteria at a middle page */
        $criteria = Criteria::fromQueryWithDefaultSchema(
            request: Query::from(parameters: ['page' => ['number' => '3', 'size' => '20']])
        );

        /** @And a page carrying a total spanning twenty-four pages */
        $result = $criteria->page(items: range(1, 20), total: 480);

        /** @When rendering the navigation preserving a filter and a sort */
        $links = Links::from(
            self: Pagination::fromPage(page: 3, perPage: 20),
            sort: Sort::fromExpression(expression: '-created_at,id'),
            filter: Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL),
            baseUri: '/v1/orders',
            navigation: $result->navigation()
        );

        /** @Then the navigation exposes every relation in semantic order preserving filter and sort */
        self::assertSame([
            'self'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=3&page[size]=20',
            'first' => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=1&page[size]=20',
            'prev'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=2&page[size]=20',
            'next'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=4&page[size]=20',
            'last'  => '/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=24&page[size]=20'
        ], $links->toArray());
    }

    public function testConstructorWhenInvokedThroughReflectionThenInstantiatesTheStaticOnlyRenderer(): void
    {
        /** @Given an uninitialized instance of the static-only RSQL renderer */
        $renderer = new ReflectionClass(Renderer::class)->newInstanceWithoutConstructor();

        /** @When invoking its otherwise-uncallable private constructor */
        new ReflectionMethod(Renderer::class, '__construct')->invoke($renderer);

        /** @Then the static-only RSQL renderer is instantiated */
        self::assertInstanceOf(Renderer::class, $renderer);
    }

    public function testToArrayWhenValueCarriesUnsafeAndStructuralCharactersThenEncodesOnlyTheUnsafeOnes(): void
    {
        /** @Given a comparison whose value carries query-unsafe and RSQL-structural characters */
        $filter = Comparison::of(field: 'name', values: ["a&b#c%d=e;f,g(h)i!j k\r\n"], operator: Operator::EQUAL);

        /** @And the readable link the decoded query is expected to round-trip to */
        $template = '/v1/orders?filter=%s&page[number]=1&page[size]=20';

        /** @When rendering the self link for a page carrying that filter */
        $self = Links::from(
            self: Pagination::fromPage(page: 1, perPage: 20),
            sort: Sort::fromExpression(expression: ''),
            filter: $filter,
            baseUri: '/v1/orders',
            navigation: Navigation::empty()
        )->toArray()['self'];

        /** @Then the unsafe characters stay percent-encoded while the structural and unreserved ones stay readable */
        self::assertSame(
            '/v1/orders?filter=name==%22a%26b%23c%25d=e;f,g(h)i!j%20k%0D%0A%22&page[number]=1&page[size]=20',
            $self
        );

        /** @And URL-decoding the link recovers the readable form carrying the rendered RSQL */
        self::assertSame(sprintf($template, Renderer::from(filter: $filter)), rawurldecode($self));
    }
}
