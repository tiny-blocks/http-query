<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\FilterExpressionIsInvalid;
use TinyBlocks\HttpQuery\Exceptions\FilterFieldNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\FilterOperatorNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\FilterShapeNotSupported;
use TinyBlocks\HttpQuery\Exceptions\FilterValueNotAllowed;
use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Operator;
use TinyBlocks\HttpQuery\Schema;
use TinyBlocks\HttpQuery\ValueKind;

final class FilterTest extends TestCase
{
    private Schema $schema;

    protected function setUp(): void
    {
        $this->schema = Schema::create()
            ->filterable(field: 'a', operators: Operator::cases())
            ->filterable(field: 'b', operators: Operator::cases())
            ->filterable(field: 'c', operators: Operator::cases())
            ->filterable(field: 'role', operators: Operator::cases())
            ->filterable(field: 'name', operators: Operator::cases())
            ->filterable(field: 'status', operators: Operator::cases());
    }

    public function testFromQueryWhenNoFilterThenComparisonsAreEmpty(): void
    {
        /** @Given a query carrying no filter parameter at all */
        $query = Query::from(parameters: []);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then there is no comparison */
        self::assertSame([], $comparisons);
    }

    public function testFromQueryWhenAndGroupThenComparisonsCarryEveryLeaf(): void
    {
        /** @Given a query carrying an AND expression of two comparisons */
        $query = Query::from(parameters: ['filter' => 'a==1;b==2']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the comparisons carry both leaves in order */
        self::assertEquals([
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
            Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
        ], $comparisons);
    }

    public function testFromQueryWhenInListThenComparisonCarriesEveryValue(): void
    {
        /** @Given a query carrying an IN list comparison */
        $query = Query::from(parameters: ['filter' => 'role=in=(admin,user)']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the only comparison is an IN comparison carrying every listed value in order */
        self::assertEquals(
            [Comparison::of(field: 'role', values: ['admin', 'user'], operator: Operator::IN)],
            $comparisons
        );
    }

    public function testFromQueryWhenNotInListThenComparisonCarriesEveryValue(): void
    {
        /** @Given a query carrying a NOT_IN list comparison */
        $query = Query::from(parameters: ['filter' => 'role=out=(a,b)']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the only comparison is a NOT_IN comparison carrying every listed value in order */
        self::assertEquals(
            [Comparison::of(field: 'role', values: ['a', 'b'], operator: Operator::NOT_IN)],
            $comparisons
        );
    }

    public function testFromQueryWhenOrGroupThenThrowsFilterShapeNotSupported(): void
    {
        /** @Given a query carrying an OR expression of two comparisons */
        $query = Query::from(parameters: ['filter' => 'a==1,b==2']);

        /** @Then an exception carrying the raw filter query string is raised */
        $this->expectException(FilterShapeNotSupported::class);
        $this->expectExceptionMessage('Filter shape <a==1,b==2> is not supported.');

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $this->schema, request: $query);
    }

    public function testFromQueryWhenDoubleQuotedValueThenComparisonStripsQuotes(): void
    {
        /** @Given a query carrying a double-quoted value with whitespace */
        $query = Query::from(parameters: ['filter' => 'name=="John Doe"']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the comparison carries the value with the surrounding quotes stripped */
        self::assertEquals(
            [Comparison::of(field: 'name', values: ['John Doe'], operator: Operator::EQUAL)],
            $comparisons
        );
    }

    public function testFromQueryWhenSingleQuotedValueThenComparisonStripsQuotes(): void
    {
        /** @Given a query carrying a single-quoted value with whitespace */
        $query = Query::from(parameters: ['filter' => "name=='John Doe'"]);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the comparison carries the value with the surrounding quotes stripped */
        self::assertEquals(
            [Comparison::of(field: 'name', values: ['John Doe'], operator: Operator::EQUAL)],
            $comparisons
        );
    }

    public function testFromQueryWhenNestedGroupThenThrowsFilterShapeNotSupported(): void
    {
        /** @Given a query whose parentheses nest an OR group inside an AND group */
        $query = Query::from(parameters: ['filter' => '(a==1,b==2);c==3']);

        /** @Then an exception carrying the raw filter query string is raised */
        $this->expectException(FilterShapeNotSupported::class);
        $this->expectExceptionMessage('Filter shape <(a==1,b==2);c==3> is not supported.');

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $this->schema, request: $query);
    }

    public function testFromQueryWhenEscapedQuoteInValueThenComparisonKeepsTheQuote(): void
    {
        /** @Given a query whose double-quoted value carries an escaped double quote */
        $query = Query::from(parameters: ['filter' => 'name=="a\\"b"']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the comparison carries the value with the escaped quote unescaped */
        self::assertEquals(
            [Comparison::of(field: 'name', values: ['a"b'], operator: Operator::EQUAL)],
            $comparisons
        );
    }

    public function testFromQueryWhenFieldNotAllowedThenThrowsFilterFieldNotAllowed(): void
    {
        /** @Given a schema allowing only the status field */
        $schema = Schema::create()->filterable(field: 'status', operators: [Operator::EQUAL]);

        /** @And a query filtering by a field that was never allowed */
        $query = Query::from(parameters: ['filter' => 'discount==10']);

        /** @Then an exception indicating the filter field is not allowed is raised */
        $this->expectException(FilterFieldNotAllowed::class);
        $this->expectExceptionMessage('Filter field <discount> is not allowed.');

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $schema, request: $query);
    }

    public function testFromQueryWhenValueBreaksKindThenThrowsFilterValueNotAllowed(): void
    {
        /** @Given a schema expecting a date-time kind on the created_at field */
        $schema = Schema::create()->filterable(
            field: 'created_at',
            operators: [Operator::GREATER_THAN],
            valueKind: ValueKind::DATETIME
        );

        /** @And a query whose value is not a valid date-time */
        $query = Query::from(parameters: ['filter' => 'created_at=gt=not-a-date']);

        /** @Then an exception indicating the value does not match the expected kind is raised */
        $this->expectException(FilterValueNotAllowed::class);
        $this->expectExceptionMessage('does not match the datetime kind');

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $schema, request: $query);
    }

    public function testFromQueryWhenParenthesizedComparisonThenComparisonIsReturned(): void
    {
        /** @Given a query wrapping a single comparison in parentheses */
        $query = Query::from(parameters: ['filter' => '(status==paid)']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the only comparison is the unwrapped equality */
        self::assertEquals(
            [Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL)],
            $comparisons
        );
    }

    public function testFromQueryWhenDateTimeValueMatchesKindThenComparisonIsReturned(): void
    {
        /** @Given a schema allowing a date-time field under the greater-than operator */
        $schema = Schema::create()->filterable(
            field: 'created_at',
            operators: [Operator::GREATER_THAN],
            valueKind: ValueKind::DATETIME
        );

        /** @And a query carrying a valid date-time value */
        $query = Query::from(parameters: ['filter' => 'created_at=gt=2023-01-15T10:30:00Z']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $schema, request: $query)->comparisons();

        /** @Then the only comparison carries the date-time value */
        self::assertEquals([Comparison::of(
            field: 'created_at',
            values: ['2023-01-15T10:30:00Z'],
            operator: Operator::GREATER_THAN
        )], $comparisons);
    }

    public function testFromQueryWhenMixedPrecedenceThenThrowsFilterShapeNotSupported(): void
    {
        /** @Given a query mixing AND and OR connectives so the top filter is an OR group */
        $query = Query::from(parameters: ['filter' => 'a==1;b==2,c==3']);

        /** @Then an exception indicating the filter shape is not supported is raised */
        $this->expectException(FilterShapeNotSupported::class);
        $this->expectExceptionMessage('is not supported');

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $this->schema, request: $query);
    }

    public function testFromQueryWhenValueNotPermittedThenThrowsFilterValueNotAllowed(): void
    {
        /** @Given a schema permitting only a fixed set of status values */
        $schema = Schema::create()->filterable(
            field: 'status',
            operators: [Operator::EQUAL],
            allowedValues: ['paid', 'pending']
        );

        /** @And a query filtering by a value outside the permitted set */
        $query = Query::from(parameters: ['filter' => 'status==shipped']);

        /** @Then an exception indicating the value is not permitted is raised */
        $this->expectException(FilterValueNotAllowed::class);
        $this->expectExceptionMessage('Value <shipped> is not permitted for filter field <status>.');

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $schema, request: $query);
    }

    public function testFromQueryWhenParenthesizedAndGroupThenComparisonsCarryEveryLeaf(): void
    {
        /** @Given a query wrapping an AND expression of two comparisons in parentheses */
        $query = Query::from(parameters: ['filter' => '(a==1;b==2)']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the comparisons carry both leaves in order */
        self::assertEquals([
            Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
            Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
        ], $comparisons);
    }

    public function testFromQueryWhenSingleComparisonThenComparisonCarriesFieldAndValue(): void
    {
        /** @Given a query carrying a single equality comparison */
        $query = Query::from(parameters: ['filter' => 'status==paid']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the only comparison is an equality on the named field with its value */
        self::assertEquals(
            [Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL)],
            $comparisons
        );
    }

    #[DataProvider('comparisonOperatorCases')]
    public function testFromQueryWhenComparisonOperatorThenComparisonCarriesThatOperator(
        string $expression,
        Operator $expected
    ): void {
        /** @Given a query carrying a binary comparison using one RSQL operator */
        $query = Query::from(parameters: ['filter' => $expression]);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the only comparison carries the expected operator */
        self::assertEquals([Comparison::of(field: 'a', values: ['b'], operator: $expected)], $comparisons);
    }

    public function testFromQueryWhenOperatorNotAllowedThenThrowsFilterOperatorNotAllowed(): void
    {
        /** @Given a schema allowing the status field under equality only */
        $schema = Schema::create()->filterable(field: 'status', operators: [Operator::EQUAL]);

        /** @And a query filtering the field with a disallowed operator */
        $query = Query::from(parameters: ['filter' => 'status!=paid']);

        /** @Then an exception indicating the filter operator is not allowed is raised */
        $this->expectException(FilterOperatorNotAllowed::class);
        $this->expectExceptionMessage('Operator <!=> is not allowed for filter field <status>.');

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $schema, request: $query);
    }

    public function testFromQueryWhenFilterNestedToTheMaximumDepthThenComparisonIsReturned(): void
    {
        /** @Given a filter template wrapping a comparison in placeholders */
        $template = '%sa==1%s';

        /** @And a query whose filter nests parentheses up to the maximum depth */
        $query = Query::from(parameters: [
            'filter' => sprintf($template, str_repeat('(', 32), str_repeat(')', 32))
        ]);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the deeply nested parentheses collapse to the single unwrapped comparison */
        self::assertEquals(
            [Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL)],
            $comparisons
        );
    }

    public function testFromQueryWhenEscapedBackslashInValueThenComparisonKeepsTheBackslash(): void
    {
        /** @Given a query whose double-quoted value carries an escaped backslash */
        $query = Query::from(parameters: ['filter' => 'name=="a\\\\b"']);

        /** @When reading the validated comparisons */
        $comparisons = Criteria::fromQuery(schema: $this->schema, request: $query)->comparisons();

        /** @Then the comparison carries the value with the escaped backslash unescaped */
        self::assertEquals(
            [Comparison::of(field: 'name', values: ['a\\b'], operator: Operator::EQUAL)],
            $comparisons
        );
    }

    #[DataProvider('malformedExpressionCases')]
    public function testFromQueryWhenMalformedExpressionThenThrowsFilterExpressionIsInvalid(
        string $expression
    ): void {
        /** @Given a query carrying a filter expression that breaks the RSQL grammar */
        $query = Query::from(parameters: ['filter' => $expression]);

        /** @Then an exception indicating the filter expression is invalid is raised */
        $this->expectException(FilterExpressionIsInvalid::class);
        $this->expectExceptionMessage('could not be parsed');

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $this->schema, request: $query);
    }

    public function testFromQueryWhenInListHasDisallowedValueThenThrowsFilterValueNotAllowed(): void
    {
        /** @Given a schema permitting only a fixed set of status values under IN */
        $schema = Schema::create()->filterable(
            field: 'status',
            operators: [Operator::IN],
            allowedValues: ['paid', 'pending']
        );

        /** @And a query whose IN list carries a value outside the permitted set */
        $query = Query::from(parameters: ['filter' => 'status=in=(paid,shipped)']);

        /** @Then an exception indicating the filter value is not allowed is raised */
        $this->expectException(FilterValueNotAllowed::class);

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $schema, request: $query);
    }

    public function testFromQueryWhenFilterNestedBeyondTheMaximumDepthThenThrowsFilterExpressionIsInvalid(): void
    {
        /** @Given a filter template wrapping a comparison in placeholders */
        $template = '%sa==1%s';

        /** @And a query whose filter nests parentheses one level beyond the maximum depth */
        $query = Query::from(parameters: [
            'filter' => sprintf($template, str_repeat('(', 33), str_repeat(')', 33))
        ]);

        /** @Then an exception indicating the filter expression is invalid is raised */
        $this->expectException(FilterExpressionIsInvalid::class);
        $this->expectExceptionMessage('could not be parsed');

        /** @When building the criteria from the query */
        Criteria::fromQuery(schema: $this->schema, request: $query);
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
            'Backslash at the end'     => ['expression' => 'name=="a\\'],
            'Unclosed group'           => ['expression' => '(status==paid'],
            'Trailing semicolon'       => ['expression' => 'status==paid;'],
            'Unknown operator'         => ['expression' => 'status=zz=paid'],
            'Whitespace after value'   => ['expression' => 'a==b c'],
            'Trailing close mark'      => ['expression' => 'a==1)'],
            'Unclosed quoted value'    => ['expression' => 'name=="x'],
            'Group missing close mark' => ['expression' => '(a==1 ']
        ];
    }
}
