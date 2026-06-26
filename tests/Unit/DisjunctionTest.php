<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Cursor\Criteria as CursorCriteria;
use TinyBlocks\HttpQuery\Exceptions\FilterFieldNotAllowed;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\LogicalOperator;
use TinyBlocks\HttpQuery\Offset\Criteria as OffsetCriteria;
use TinyBlocks\HttpQuery\Operator;
use TinyBlocks\HttpQuery\Schema;

final class DisjunctionTest extends TestCase
{
    public function testCursorWhenDisjunctionAllowedThenExposesTheOrTree(): void
    {
        /** @Given a cursor schema allowing disjunction */
        $schema = Schema::create()
            ->filterable(field: 'a', operators: Operator::cases())
            ->filterable(field: 'b', operators: Operator::cases())
            ->allowDisjunction();

        /** @And a query carrying an OR expression */
        $query = Query::from(parameters: ['filter' => 'a==1,b==2']);

        /** @When building the cursor criteria */
        $criteria = CursorCriteria::fromQuery(request: $query, schema: $schema);

        /** @Then the filter tree is the OR group */
        self::assertEquals(
            Group::of(
                filters: [
                    Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                    Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
                ],
                operator: LogicalOperator::OR
            ),
            $criteria->filter()
        );
    }

    public function testWhenDisjunctionAllowedThenValidatesEveryNestedLeaf(): void
    {
        /** @Given a schema allowing disjunction over three fields */
        $schema = Schema::create()
            ->filterable(field: 'a', operators: Operator::cases())
            ->filterable(field: 'b', operators: Operator::cases())
            ->filterable(field: 'c', operators: Operator::cases())
            ->allowDisjunction();

        /** @And a query nesting an OR group inside an AND group */
        $query = Query::from(parameters: ['filter' => '(a==1,b==2);c==3']);

        /** @When building the criteria */
        $criteria = OffsetCriteria::fromQuery(request: $query, schema: $schema);

        /** @Then every nested leaf is validated and returned in tree order */
        self::assertEquals(
            [
                Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL),
                Comparison::of(field: 'c', values: ['3'], operator: Operator::EQUAL)
            ],
            $criteria->comparisons()
        );
    }

    public function testWhenDisjunctionAllowedAndFieldNotAllowedThenStillThrows(): void
    {
        /** @Given a schema allowing disjunction but only the status field */
        $schema = Schema::create()
            ->filterable(field: 'status', operators: [Operator::EQUAL])
            ->allowDisjunction();

        /** @And an OR query whose second leaf targets a field that was never allowed */
        $query = Query::from(parameters: ['filter' => 'status==paid,discount==10']);

        /** @Then the field allowlist is still enforced inside the disjunction */
        $this->expectException(FilterFieldNotAllowed::class);
        $this->expectExceptionMessage('Filter field <discount> is not allowed.');

        /** @When building the criteria */
        OffsetCriteria::fromQuery(request: $query, schema: $schema);
    }

    public function testOffsetWhenDisjunctionAllowedThenAcceptsTheOrGroupAndExposesTheTree(): void
    {
        /** @Given an offset schema allowing disjunction */
        $schema = Schema::create()
            ->filterable(field: 'a', operators: Operator::cases())
            ->filterable(field: 'b', operators: Operator::cases())
            ->allowDisjunction();

        /** @And a query carrying an OR expression */
        $query = Query::from(parameters: ['filter' => 'a==1,b==2']);

        /** @When building the offset criteria */
        $criteria = OffsetCriteria::fromQuery(request: $query, schema: $schema);

        /** @Then the filter tree is the OR group and the comparisons carry every validated leaf */
        self::assertEquals(
            Group::of(
                filters: [
                    Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                    Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
                ],
                operator: LogicalOperator::OR
            ),
            $criteria->filter()
        );
        self::assertEquals(
            [
                Comparison::of(field: 'a', values: ['1'], operator: Operator::EQUAL),
                Comparison::of(field: 'b', values: ['2'], operator: Operator::EQUAL)
            ],
            $criteria->comparisons()
        );
    }
}
