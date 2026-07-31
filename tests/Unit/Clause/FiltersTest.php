<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Clause;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use TinyBlocks\HttpQuery\Clause\FilterColumn;
use TinyBlocks\HttpQuery\Clause\FilterColumns;
use TinyBlocks\HttpQuery\Clause\Filters;
use TinyBlocks\HttpQuery\Clause\Fragment;
use TinyBlocks\HttpQuery\Clause\OperatorRenderer;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\FilterColumnNotMapped;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\Internal\AnchoredPrefix;
use TinyBlocks\HttpQuery\LogicalOperator;
use TinyBlocks\HttpQuery\Operator;

final class FiltersTest extends TestCase
{
    public function testFromWhenColumnIsBooleanThenBindsTrueAsOne(): void
    {
        /** @Given a comparison over a boolean field with the value true */
        $comparison = Comparison::of(field: 'is_active', values: ['true'], operator: Operator::EQUAL);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->boolean(field: 'is_active', column: 'pme.is_active'),
            comparisons: [$comparison]
        );

        /** @Then the value binds as 1 */
        self::assertSame('pme.is_active = :filter_0', $filters->sql());
        self::assertSame(['filter_0' => 1], $filters->parameters());
    }

    public function testFromWhenOperatorStartsWithThenRendersAnchoredLikeAndAppendsTheWildcard(): void
    {
        /** @Given a prefix comparison over a name field */
        $comparison = Comparison::of(field: 'name', values: ['jose'], operator: Operator::STARTS_WITH);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'name', column: 'cli.name'),
            comparisons: [$comparison]
        );

        /** @Then the predicate anchors the prefix and only the trailing wildcard is added */
        self::assertSame("cli.name LIKE :filter_0 ESCAPE '!'", $filters->sql());
        self::assertSame(['filter_0' => 'jose%'], $filters->parameters());
    }

    #[DataProvider('wildcardCases')]
    public function testFromWhenPrefixCarriesAWildcardThenNeutralizesIt(string $value, string $bound): void
    {
        /** @Given a prefix whose raw value carries a character LIKE would read as a wildcard or an escape */

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'name', column: 'cli.name'),
            comparisons: [Comparison::of(field: 'name', values: [$value], operator: Operator::STARTS_WITH)]
        );

        /** @Then the character binds escaped, so it matches literally instead of expanding */
        self::assertSame(['filter_0' => $bound], $filters->parameters());
    }

    public function testConstructorWhenInvokedThroughReflectionThenInstantiatesTheStaticOnlyPrefix(): void
    {
        /** @Given an uninitialized instance of the static-only prefix anchor */
        $anchor = new ReflectionClass(AnchoredPrefix::class)->newInstanceWithoutConstructor();

        /** @When invoking its otherwise-uncallable private constructor */
        new ReflectionMethod(AnchoredPrefix::class, '__construct')->invoke($anchor);

        /** @Then the static-only prefix anchor is instantiated */
        self::assertInstanceOf(AnchoredPrefix::class, $anchor);
    }

    public static function wildcardCases(): array
    {
        return [
            'percent'     => ['value' => '100%', 'bound' => '100!%%'],
            'escape char' => ['value' => 'a!b', 'bound' => 'a!!b%'],
            'underscore'  => ['value' => 'a_b', 'bound' => 'a!_b%'],
            'backslash'   => ['value' => 'a\\b', 'bound' => 'a\\b%'],
            'no wildcard' => ['value' => 'jose', 'bound' => 'jose%']
        ];
    }

    public function testFromWhenFieldHasNoColumnMappingThenThrows(): void
    {
        /** @Given a comparison over a field absent from the column mapping */
        $comparison = Comparison::of(field: 'status', values: ['PAID'], operator: Operator::EQUAL);

        /** @Then an exception indicating the field has no column mapping should be thrown */
        $this->expectException(FilterColumnNotMapped::class);
        $this->expectExceptionMessage('Filter field <status> has no column mapping.');

        /** @When the predicate is assembled without a mapping for that field */
        Filters::from(columns: FilterColumns::create(), comparisons: [$comparison]);
    }

    public function testFromTreeWhenGroupHasAnEmptyChildThenDropsIt(): void
    {
        /** @Given an AND group carrying a comparison, an empty nested group, then another comparison */
        $tree = Group::of(
            filters: [
                Comparison::of(field: 'status', values: ['PAID'], operator: Operator::EQUAL),
                Group::none(),
                Comparison::of(field: 'context', values: ['checkout'], operator: Operator::EQUAL)
            ],
            operator: LogicalOperator::AND
        );

        /** @When the tree is rendered */
        $filters = Filters::fromTree(
            filter: $tree,
            columns: FilterColumns::create()
                ->plain(field: 'status', column: 'pay.status')
                ->plain(field: 'context', column: 'poc.code')
        );

        /** @Then the empty child is dropped while the comparison after it is kept */
        self::assertSame('(pay.status = :filter_0 AND poc.code = :filter_1)', $filters->sql());
        self::assertSame(['filter_0' => 'PAID', 'filter_1' => 'checkout'], $filters->parameters());
    }

    public function testFromWhenColumnIsBooleanThenBindsFalseAsZero(): void
    {
        /** @Given a comparison over a boolean field with the value false */
        $comparison = Comparison::of(field: 'is_active', values: ['false'], operator: Operator::EQUAL);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->boolean(field: 'is_active', column: 'pme.is_active'),
            comparisons: [$comparison]
        );

        /** @Then the value binds as 0 */
        self::assertSame('pme.is_active = :filter_0', $filters->sql());
        self::assertSame(['filter_0' => 0], $filters->parameters());
    }

    public function testFromWhenOperatorIsInThenRendersAnInPredicate(): void
    {
        /** @Given a comparison listing several values */
        $comparison = Comparison::of(field: 'status', values: ['PAID', 'PENDING'], operator: Operator::IN);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'status', column: 'pay.status'),
            comparisons: [$comparison]
        );

        /** @Then the predicate renders an IN over one placeholder per value */
        self::assertSame('pay.status IN (:filter_0, :filter_1)', $filters->sql());
        self::assertSame(['filter_0' => 'PAID', 'filter_1' => 'PENDING'], $filters->parameters());
    }

    public function testFromWhenColumnIsWrappedThenWrapsThePlaceholder(): void
    {
        /** @Given a comparison over a field whose column wraps the placeholder */
        $comparison = Comparison::of(field: 'method_id', values: ['9d9d2a4f'], operator: Operator::EQUAL);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->wrapped(
                field: 'method_id',
                column: 'prr.method_id',
                binding: 'UUID_TO_BIN(%s)'
            ),
            comparisons: [$comparison]
        );

        /** @Then the placeholder is wrapped by the binding */
        self::assertSame('prr.method_id = UUID_TO_BIN(:filter_0)', $filters->sql());
        self::assertSame(['filter_0' => '9d9d2a4f'], $filters->parameters());
    }

    public function testFromTreeWhenOrGroupThenJoinsWithOrInParentheses(): void
    {
        /** @Given an OR group of two comparisons */
        $tree = Group::of(
            filters: [
                Comparison::of(field: 'status', values: ['PAID'], operator: Operator::EQUAL),
                Comparison::of(field: 'context', values: ['checkout'], operator: Operator::EQUAL)
            ],
            operator: LogicalOperator::OR
        );

        /** @When the tree is rendered */
        $filters = Filters::fromTree(
            filter: $tree,
            columns: FilterColumns::create()
                ->plain(field: 'status', column: 'pay.status')
                ->plain(field: 'context', column: 'poc.code')
        );

        /** @Then the children are joined with OR inside parentheses */
        self::assertSame('(pay.status = :filter_0 OR poc.code = :filter_1)', $filters->sql());
        self::assertSame(['filter_0' => 'PAID', 'filter_1' => 'checkout'], $filters->parameters());
    }

    public function testFromWhenMultipleComparisonsThenJoinsThemWithAnd(): void
    {
        /** @Given a comparison over a mapped field */
        $status = Comparison::of(field: 'status', values: ['PAID'], operator: Operator::EQUAL);

        /** @And a second comparison over another mapped field */
        $context = Comparison::of(field: 'context', values: ['checkout'], operator: Operator::EQUAL);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()
                ->plain(field: 'status', column: 'pay.status')
                ->plain(field: 'context', column: 'poc.code'),
            comparisons: [$status, $context]
        );

        /** @Then the predicate joins both comparisons with AND over distinct placeholders */
        self::assertSame('pay.status = :filter_0 AND poc.code = :filter_1', $filters->sql());
        self::assertSame(['filter_0' => 'PAID', 'filter_1' => 'checkout'], $filters->parameters());
    }

    public function testFromWhenNoComparisonsThenYieldsAnEmptyPredicate(): void
    {
        /** @Given a column mapping with no comparisons to apply */
        $columns = FilterColumns::create()->plain(field: 'status', column: 'pay.status');

        /** @When the predicate is assembled over no comparisons */
        $filters = Filters::from(columns: $columns, comparisons: []);

        /** @Then the predicate and its parameters are empty */
        self::assertTrue($filters->isEmpty());
        self::assertSame('', $filters->sql());
        self::assertSame([], $filters->parameters());
    }

    public function testFromTreeWhenEmptyGroupThenYieldsAnEmptyPredicate(): void
    {
        /** @Given an empty group */
        $tree = Group::none();

        /** @When the tree is rendered */
        $filters = Filters::fromTree(
            filter: $tree,
            columns: FilterColumns::create()->plain(field: 'status', column: 'pay.status')
        );

        /** @Then the predicate and its parameters are empty */
        self::assertTrue($filters->isEmpty());
        self::assertSame('', $filters->sql());
        self::assertSame([], $filters->parameters());
    }

    public function testFromWhenOperatorIsLessThanThenRendersAComparison(): void
    {
        /** @Given a comparison with a strict upper bound */
        $comparison = Comparison::of(field: 'created_at', values: ['2026-12-31'], operator: Operator::LESS_THAN);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'created_at', column: 'pay.created_at'),
            comparisons: [$comparison]
        );

        /** @Then the predicate renders a strict less-than comparison */
        self::assertSame('pay.created_at < :filter_0', $filters->sql());
        self::assertSame(['filter_0' => '2026-12-31'], $filters->parameters());
    }

    public function testFromTreeWhenAndGroupThenJoinsWithAndInParentheses(): void
    {
        /** @Given an AND group of two comparisons */
        $tree = Group::of(
            filters: [
                Comparison::of(field: 'status', values: ['PAID'], operator: Operator::EQUAL),
                Comparison::of(field: 'context', values: ['checkout'], operator: Operator::EQUAL)
            ],
            operator: LogicalOperator::AND
        );

        /** @When the tree is rendered */
        $filters = Filters::fromTree(
            filter: $tree,
            columns: FilterColumns::create()
                ->plain(field: 'status', column: 'pay.status')
                ->plain(field: 'context', column: 'poc.code')
        );

        /** @Then the children are joined with AND inside parentheses */
        self::assertSame('(pay.status = :filter_0 AND poc.code = :filter_1)', $filters->sql());
        self::assertSame(['filter_0' => 'PAID', 'filter_1' => 'checkout'], $filters->parameters());
    }

    public function testFromWhenOperatorIsNotEqualThenRendersAnInequality(): void
    {
        /** @Given a comparison excluding a value */
        $comparison = Comparison::of(field: 'status', values: ['PAID'], operator: Operator::NOT_EQUAL);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'status', column: 'pay.status'),
            comparisons: [$comparison]
        );

        /** @Then the predicate renders an inequality, not an equality */
        self::assertSame('pay.status <> :filter_0', $filters->sql());
        self::assertSame(['filter_0' => 'PAID'], $filters->parameters());
    }

    public function testFromWhenOperatorIsNotInThenRendersANotInPredicate(): void
    {
        /** @Given a comparison excluding several values */
        $comparison = Comparison::of(field: 'status', values: ['PAID', 'PENDING'], operator: Operator::NOT_IN);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'status', column: 'pay.status'),
            comparisons: [$comparison]
        );

        /** @Then the predicate renders a NOT IN over one placeholder per value, keeping every value */
        self::assertSame('pay.status NOT IN (:filter_0, :filter_1)', $filters->sql());
        self::assertSame(['filter_0' => 'PAID', 'filter_1' => 'PENDING'], $filters->parameters());
    }

    public function testFromWhenOperatorIsGreaterThanThenRendersAComparison(): void
    {
        /** @Given a comparison with a strict lower bound */
        $comparison = Comparison::of(field: 'created_at', values: ['2026-01-01'], operator: Operator::GREATER_THAN);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'created_at', column: 'pay.created_at'),
            comparisons: [$comparison]
        );

        /** @Then the predicate renders a strict greater-than comparison */
        self::assertSame('pay.created_at > :filter_0', $filters->sql());
        self::assertSame(['filter_0' => '2026-01-01'], $filters->parameters());
    }

    public function testFromTreeWhenCustomRendererThenAppliesItAcrossTheTree(): void
    {
        /** @Given an OR group whose comparisons a custom renderer handles */
        $tree = Group::of(
            filters: [
                Comparison::of(field: 'name', values: ['acme'], operator: Operator::EQUAL),
                Comparison::of(field: 'code', values: ['pix'], operator: Operator::EQUAL)
            ],
            operator: LogicalOperator::OR
        );

        /** @And a custom renderer rendering equality as a case-insensitive contains */
        $renderer = new class implements OperatorRenderer {
            public function render(FilterColumn $column, int $offset, Comparison $comparison): Fragment
            {
                $name = sprintf('filter_%d', $offset);
                $bind = sprintf($column->binding(), sprintf(':%s', $name));

                return Fragment::of(
                    sql: sprintf('LOWER(%s) LIKE LOWER(%s)', $column->column(), $bind),
                    parameters: [$name => sprintf('%%%s%%', $comparison->firstValue())]
                );
            }

            public function supports(Operator $operator): bool
            {
                return $operator === Operator::EQUAL;
            }
        };

        /** @When the tree is rendered with the custom renderer */
        $filters = Filters::fromTree(
            $tree,
            FilterColumns::create()
                ->plain(field: 'name', column: 'pme.name')
                ->plain(field: 'code', column: 'pme.code'),
            $renderer
        );

        /** @Then the custom rendering is applied to every comparison in the tree */
        self::assertSame(
            '(LOWER(pme.name) LIKE LOWER(:filter_0) OR LOWER(pme.code) LIKE LOWER(:filter_1))',
            $filters->sql()
        );
        self::assertSame(['filter_0' => '%acme%', 'filter_1' => '%pix%'], $filters->parameters());
    }

    public function testFromWhenFieldIsMappedThenBindsTheValueToAPlaceholder(): void
    {
        /** @Given a comparison over a field that has a column mapping */
        $comparison = Comparison::of(field: 'status', values: ['PAID'], operator: Operator::EQUAL);

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'status', column: 'pay.status'),
            comparisons: [$comparison]
        );

        /** @Then the predicate binds the value to a generated placeholder */
        self::assertSame('pay.status = :filter_0', $filters->sql());
        self::assertSame(['filter_0' => 'PAID'], $filters->parameters());
    }

    public function testFromWhenOperatorIsLessThanOrEqualThenRendersAComparison(): void
    {
        /** @Given a comparison with an upper bound */
        $comparison = Comparison::of(
            field: 'created_at',
            values: ['2026-12-31'],
            operator: Operator::LESS_THAN_OR_EQUAL
        );

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'created_at', column: 'pay.created_at'),
            comparisons: [$comparison]
        );

        /** @Then the predicate renders a less-than-or-equal comparison */
        self::assertSame('pay.created_at <= :filter_0', $filters->sql());
        self::assertSame(['filter_0' => '2026-12-31'], $filters->parameters());
    }

    public function testFromTreeWhenGroupHasOneChildThenRendersWithoutParentheses(): void
    {
        /** @Given a group carrying a single comparison */
        $tree = Group::of(
            filters: [Comparison::of(field: 'status', values: ['PAID'], operator: Operator::EQUAL)],
            operator: LogicalOperator::AND
        );

        /** @When the tree is rendered */
        $filters = Filters::fromTree(
            filter: $tree,
            columns: FilterColumns::create()->plain(field: 'status', column: 'pay.status')
        );

        /** @Then the single child renders without wrapping parentheses */
        self::assertSame('pay.status = :filter_0', $filters->sql());
        self::assertSame(['filter_0' => 'PAID'], $filters->parameters());
    }

    public function testFromTreeWhenSingleComparisonThenRendersWithoutParentheses(): void
    {
        /** @Given a bare comparison, not a group */
        $tree = Comparison::of(field: 'status', values: ['PAID'], operator: Operator::EQUAL);

        /** @When the tree is rendered */
        $filters = Filters::fromTree(
            filter: $tree,
            columns: FilterColumns::create()->plain(field: 'status', column: 'pay.status')
        );

        /** @Then it renders the comparison without wrapping parentheses */
        self::assertSame('pay.status = :filter_0', $filters->sql());
        self::assertSame(['filter_0' => 'PAID'], $filters->parameters());
    }

    public function testFromWhenOperatorIsGreaterThanOrEqualThenRendersAComparison(): void
    {
        /** @Given a comparison with a lower bound */
        $comparison = Comparison::of(
            field: 'created_at',
            values: ['2026-01-01'],
            operator: Operator::GREATER_THAN_OR_EQUAL
        );

        /** @When the predicate is assembled */
        $filters = Filters::from(
            columns: FilterColumns::create()->plain(field: 'created_at', column: 'pay.created_at'),
            comparisons: [$comparison]
        );

        /** @Then the predicate renders a greater-than-or-equal comparison */
        self::assertSame('pay.created_at >= :filter_0', $filters->sql());
        self::assertSame(['filter_0' => '2026-01-01'], $filters->parameters());
    }

    public function testFromWhenCustomRendererSupportsTheOperatorThenUsesItOverTheBuiltIn(): void
    {
        /** @Given a comparison whose operator a custom renderer handles */
        $comparison = Comparison::of(field: 'name', values: ['acme'], operator: Operator::EQUAL);

        /** @And a custom renderer rendering equality as a case-insensitive contains */
        $renderer = new class implements OperatorRenderer {
            public function render(FilterColumn $column, int $offset, Comparison $comparison): Fragment
            {
                $name = sprintf('filter_%d', $offset);
                $bind = sprintf($column->binding(), sprintf(':%s', $name));

                return Fragment::of(
                    sql: sprintf('LOWER(%s) LIKE LOWER(%s)', $column->column(), $bind),
                    parameters: [$name => sprintf('%%%s%%', $comparison->firstValue())]
                );
            }

            public function supports(Operator $operator): bool
            {
                return $operator === Operator::EQUAL;
            }
        };

        /** @When the predicate is assembled with the custom renderer */
        $filters = Filters::from(
            FilterColumns::create()->plain(field: 'name', column: 'pme.name'),
            [$comparison],
            $renderer
        );

        /** @Then the custom rendering is used instead of the built-in equality */
        self::assertSame('LOWER(pme.name) LIKE LOWER(:filter_0)', $filters->sql());
        self::assertSame(['filter_0' => '%acme%'], $filters->parameters());
    }

    public function testFromTreeWhenNestedGroupThenRendersEachGroupParenthesizedWithThreadedOffsets(): void
    {
        /** @Given an AND group nesting an OR group */
        $tree = Group::of(
            filters: [
                Comparison::of(field: 'status', values: ['PAID'], operator: Operator::EQUAL),
                Group::of(
                    filters: [
                        Comparison::of(field: 'total', values: ['100'], operator: Operator::GREATER_THAN_OR_EQUAL),
                        Comparison::of(field: 'total', values: ['200'], operator: Operator::GREATER_THAN_OR_EQUAL)
                    ],
                    operator: LogicalOperator::OR
                )
            ],
            operator: LogicalOperator::AND
        );

        /** @When the tree is rendered */
        $filters = Filters::fromTree(
            filter: $tree,
            columns: FilterColumns::create()
                ->plain(field: 'status', column: 'pay.status')
                ->plain(field: 'total', column: 'pay.total')
        );

        /** @Then each group is parenthesized and the placeholder offsets are threaded across the tree */
        self::assertSame(
            '(pay.status = :filter_0 AND (pay.total >= :filter_1 OR pay.total >= :filter_2))',
            $filters->sql()
        );
        self::assertSame(['filter_0' => 'PAID', 'filter_1' => '100', 'filter_2' => '200'], $filters->parameters());
    }
}
