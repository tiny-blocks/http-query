<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\Exceptions\FilterExpressionIsInvalid;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\LogicalOperator;
use TinyBlocks\HttpQuery\Operator;

final class FilterTest extends TestCase
{
    public function testIsEmptyWhenGroupHasNoChildThenIsEmpty(): void
    {
        /** @Given an empty group */
        $group = Group::none();

        /** @When checking whether it is empty */
        $isEmpty = $group->isEmpty();

        /** @Then it reports itself as empty */
        self::assertTrue($isEmpty);
    }

    public function testIsEmptyWhenGroupHasChildThenIsNotEmpty(): void
    {
        /** @Given a group joining a single comparison */
        $group = Group::of(filters: [
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND);

        /** @When checking whether it is empty */
        $isEmpty = $group->isEmpty();

        /** @Then it reports itself as not empty */
        self::assertFalse($isEmpty);
    }

    public function testIsEmptyWhenComparisonGivenThenIsNotEmpty(): void
    {
        /** @Given an equality comparison */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When checking whether it is empty */
        $isEmpty = $comparison->isEmpty();

        /** @Then it reports itself as not empty */
        self::assertFalse($isEmpty);
    }

    public function testFilteringWhenNoFilterGivenThenReturnsEmptyGroup(): void
    {
        /** @Given a query carrying no filter parameter at all */
        $query = Query::from(parameters: []);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the filter is an empty group joined by the AND connective */
        self::assertEquals(Group::of(filters: [], operator: LogicalOperator::AND), $filter);
    }

    public function testFilteringWhenOrGivenThenGroupJoinsTwoComparisons(): void
    {
        /** @Given a query carrying an OR expression of two comparisons */
        $query = Query::from(parameters: ['filter' => 'a==1,b==2']);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the filter is an OR group joining the two comparison children in order */
        self::assertEquals(Group::of(filters: [
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
            Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::OR), $filter);
    }

    public function testFilteringWhenAndGivenThenGroupJoinsTwoComparisons(): void
    {
        /** @Given a query carrying an AND expression of two comparisons */
        $query = Query::from(parameters: ['filter' => 'a==1;b==2']);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the filter is an AND group joining the two comparison children in order */
        self::assertEquals(Group::of(filters: [
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
            Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND), $filter);
    }

    public function testFilteringWhenInListGivenThenComparisonCarriesEveryValue(): void
    {
        /** @Given a query carrying an IN list comparison */
        $query = Query::from(parameters: ['filter' => 'role=in=(admin,user)']);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the filter is an IN comparison carrying every listed value in order */
        self::assertEquals(Comparison::of(field: 'role', values: ['admin', 'user'], operator: Operator::IN), $filter);
    }

    public function testToExpressionWhenAndGroupNestedInOrThenLeavesItUnwrapped(): void
    {
        /** @Given an OR group whose first child is an AND group */
        $group = Group::of(filters: [
            Group::of(filters: [
                Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
            ], operator: LogicalOperator::AND),
            Comparison::of(field: 'c', values: ['3'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::OR);

        /** @When rendering it as an RSQL expression */
        $expression = $group->toExpression()->value();

        /** @Then the tighter-binding AND group is left unwrapped */
        self::assertSame('a==1;b==2,c==3', $expression);
    }

    public function testToExpressionWhenInListGivenThenWrapsValuesInParentheses(): void
    {
        /** @Given an IN comparison carrying several values */
        $comparison = Comparison::of(field: 'role', values: ['admin', 'user'], operator: Operator::IN);

        /** @When rendering it as an RSQL expression */
        $expression = $comparison->toExpression()->value();

        /** @Then it wraps the comma-joined values in parentheses */
        self::assertSame('role=in=(admin,user)', $expression);
    }

    public function testToExpressionWhenValueCarriesReservedCharacterThenQuotesIt(): void
    {
        /** @Given a comparison whose value carries a space */
        $comparison = Comparison::of(field: 'name', values: ['John Doe'], operator: Operator::EQUAL);

        /** @When rendering it as an RSQL expression */
        $expression = $comparison->toExpression()->value();

        /** @Then it renders the value within double quotes */
        self::assertSame('name=="John Doe"', $expression);
    }

    public function testToExpressionWhenValueCarriesQuoteAndBackslashThenEscapesBoth(): void
    {
        /** @Given a comparison whose value carries a double quote and a backslash */
        $comparison = Comparison::of(field: 'name', values: ['a"b\\c'], operator: Operator::EQUAL);

        /** @When rendering it as an RSQL expression */
        $expression = $comparison->toExpression()->value();

        /** @Then the quote and the backslash are escaped within the double-quoted value */
        self::assertSame('name=="a\\"b\\\\c"', $expression);
    }

    public function testFilteringWhenMixedPrecedenceGivenThenAndBindsTighterThanOr(): void
    {
        /** @Given a query mixing AND and OR connectives without explicit grouping */
        $query = Query::from(parameters: ['filter' => 'a==1;b==2,c==3']);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the top filter is an OR group whose first child is the tighter AND group */
        self::assertEquals(Group::of(filters: [
            Group::of(filters: [
                Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
            ], operator: LogicalOperator::AND),
            Comparison::of(field: 'c', values: ['3'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::OR), $filter);
    }

    public function testFilteringWhenNotInListGivenThenComparisonCarriesEveryValue(): void
    {
        /** @Given a query carrying a NOT_IN list comparison */
        $query = Query::from(parameters: ['filter' => 'role=out=(a,b)']);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the filter is a NOT_IN comparison carrying every listed value in order */
        self::assertEquals(Comparison::of(field: 'role', values: ['a', 'b'], operator: Operator::NOT_IN), $filter);
    }

    public function testToExpressionWhenAndGroupGivenThenJoinsChildrenWithAndToken(): void
    {
        /** @Given an AND group of two comparisons */
        $group = Group::of(filters: [
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
            Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND);

        /** @When rendering it as an RSQL expression */
        $expression = $group->toExpression()->value();

        /** @Then it joins the children with the AND token */
        self::assertSame('a==1;b==2', $expression);
    }

    public function testToExpressionWhenOrGroupNestedInAndThenWrapsItInParentheses(): void
    {
        /** @Given an AND group whose first child is an OR group */
        $group = Group::of(filters: [
            Group::of(filters: [
                Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
            ], operator: LogicalOperator::OR),
            Comparison::of(field: 'c', values: ['3'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND);

        /** @When rendering it as an RSQL expression */
        $expression = $group->toExpression()->value();

        /** @Then the nested OR group is wrapped in parentheses */
        self::assertSame('(a==1,b==2);c==3', $expression);
    }

    public function testFilteringWhenDoubleQuotedValueGivenThenComparisonStripsQuotes(): void
    {
        /** @Given a query carrying a double-quoted value with whitespace */
        $query = Query::from(parameters: ['filter' => 'name=="John Doe"']);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the comparison carries the value with the surrounding quotes stripped */
        self::assertEquals(Comparison::of(field: 'name', values: ['John Doe'], operator: Operator::EQUAL), $filter);
    }

    public function testFilteringWhenSingleQuotedValueGivenThenComparisonStripsQuotes(): void
    {
        /** @Given a query carrying a single-quoted value with whitespace */
        $query = Query::from(parameters: ['filter' => "name=='John Doe'"]);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the comparison carries the value with the surrounding quotes stripped */
        self::assertEquals(Comparison::of(field: 'name', values: ['John Doe'], operator: Operator::EQUAL), $filter);
    }

    public function testToExpressionWhenComparisonGivenThenRendersFieldOperatorAndValue(): void
    {
        /** @Given an equality comparison on a field */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When rendering it as an RSQL expression */
        $expression = $comparison->toExpression()->value();

        /** @Then it renders the field, the operator token, and the value */
        self::assertSame('status==paid', $expression);
    }

    public function testFilteringWhenExplicitGroupingGivenThenParenthesesOverridePrecedence(): void
    {
        /** @Given a query whose parentheses force the OR group to bind first */
        $query = Query::from(parameters: ['filter' => '(a==1,b==2);c==3']);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the top filter is an AND group whose first child is the parenthesized OR group */
        self::assertEquals(Group::of(filters: [
            Group::of(filters: [
                Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
            ], operator: LogicalOperator::OR),
            Comparison::of(field: 'c', values: ['3'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND), $filter);
    }

    public function testFilteringWhenSingleComparisonGivenThenComparisonCarriesFieldAndValue(): void
    {
        /** @Given a query carrying a single equality comparison */
        $query = Query::from(parameters: ['filter' => 'status==paid']);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the filter is an equality comparison on the named field with its value */
        self::assertEquals(Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL), $filter);
    }

    #[DataProvider('comparisonOperatorCases')]
    public function testFilteringWhenComparisonOperatorGivenThenComparisonCarriesThatOperator(
        string $expression,
        Operator $expected
    ): void {
        /** @Given a query carrying a binary comparison using one RSQL operator */
        $query = Query::from(parameters: ['filter' => $expression]);

        /** @When reading the filtering specification */
        $filter = Criteria::fromQuery(request: $query)->filtering();

        /** @Then the filter is a comparison on the left field carrying the expected operator */
        self::assertEquals(Comparison::of(field: 'a', values: ['b'], operator: $expected), $filter);
    }

    #[DataProvider('malformedExpressionCases')]
    public function testFilteringWhenMalformedExpressionGivenThenThrowsFilterExpressionIsInvalid(
        string $expression
    ): void {
        /** @Given a query carrying a filter expression that breaks the RSQL grammar */
        $query = Query::from(parameters: ['filter' => $expression]);

        /** @Then an exception indicating the filter expression is invalid is raised */
        $this->expectException(FilterExpressionIsInvalid::class);
        $this->expectExceptionMessage('could not be parsed');

        /** @When building the criteria from the query */
        Criteria::fromQuery(request: $query);
    }

    public function testValuesWhenComparisonGivenThenReturnsTheComparedValues(): void
    {
        /** @Given an IN comparison carrying several values */
        $comparison = Comparison::of(field: 'role', values: ['admin', 'user'], operator: Operator::IN);

        /** @When reading the compared values */
        $values = $comparison->values();

        /** @Then it returns the values compared against the field in order */
        self::assertSame(['admin', 'user'], $values);
    }

    public function testOperatorWhenComparisonGivenThenReturnsTheComparisonOperator(): void
    {
        /** @Given an equality comparison */
        $comparison = Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL);

        /** @When reading the comparison operator */
        $operator = $comparison->operator();

        /** @Then it returns the operator the comparison carries */
        self::assertSame(Operator::EQUAL, $operator);
    }

    public function testOperatorWhenGroupGivenThenReturnsTheLogicalConnective(): void
    {
        /** @Given an AND group joining two comparisons */
        $group = Group::of(filters: [
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
            Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
        ], operator: LogicalOperator::AND);

        /** @When reading the logical connective */
        $operator = $group->operator();

        /** @Then it returns the connective joining the children */
        self::assertSame(LogicalOperator::AND, $operator);
    }

    public static function comparisonOperatorCases(): array
    {
        return [
            'NOT_EQUAL'             => ['expression' => 'a!=b', 'expected' => Operator::NOT_EQUAL],
            'LESS_THAN'             => ['expression' => 'a=lt=b', 'expected' => Operator::LESS_THAN],
            'GREATER_THAN'          => ['expression' => 'a=gt=b', 'expected' => Operator::GREATER_THAN],
            'LESS_THAN_OR_EQUAL'    => ['expression' => 'a=le=b', 'expected' => Operator::LESS_THAN_OR_EQUAL],
            'GREATER_THAN_OR_EQUAL' => ['expression' => 'a=ge=b', 'expected' => Operator::GREATER_THAN_OR_EQUAL]
        ];
    }

    public static function malformedExpressionCases(): array
    {
        return [
            'No operator'              => ['expression' => 'status'],
            'Empty value'              => ['expression' => 'status=='],
            'Unclosed group'           => ['expression' => '(status==paid'],
            'Trailing semicolon'       => ['expression' => 'status==paid;'],
            'Unknown operator'         => ['expression' => 'status=zz=paid'],
            'Group missing close mark' => ['expression' => '(a==1 '],
            'Trailing close mark'      => ['expression' => 'a==1)'],
            'Unclosed quoted value'    => ['expression' => 'name=="x']
        ];
    }
}
