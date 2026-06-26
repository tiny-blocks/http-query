<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Clause;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\ConstantSqlClause;
use TinyBlocks\HttpQuery\Clause\Every;

final class EveryTest extends TestCase
{
    public function testOfWhenClausesGivenThenJoinsTheNonEmptyOnesWithAnd(): void
    {
        /** @Given two non-empty clauses around an empty one */
        $first = ConstantSqlClause::from(sql: 'pay.status = :pa', parameters: ['pa' => 'PAID']);
        $empty = ConstantSqlClause::from(sql: '', parameters: []);
        $second = ConstantSqlClause::from(sql: 'poc.code = :pb', parameters: ['pb' => 'checkout']);

        /** @When they are combined */
        $every = Every::of($first, $empty, $second);

        /** @Then the empty clause is dropped, the rest joined with AND, the parameters merged */
        self::assertFalse($every->isEmpty());
        self::assertSame('pay.status = :pa AND poc.code = :pb', $every->sql());
        self::assertSame(['pa' => 'PAID', 'pb' => 'checkout'], $every->parameters());
    }

    public function testOfWhenAllClausesAreEmptyThenYieldsAnEmptyConjunction(): void
    {
        /** @Given only empty clauses */
        $empty = ConstantSqlClause::from(sql: '', parameters: []);

        /** @When they are combined */
        $every = Every::of($empty, $empty);

        /** @Then the conjunction is empty */
        self::assertTrue($every->isEmpty());
        self::assertSame('', $every->sql());
        self::assertSame([], $every->parameters());
    }
}
