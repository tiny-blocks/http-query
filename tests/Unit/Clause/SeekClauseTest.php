<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Clause;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Clause\FilterColumns;
use TinyBlocks\HttpQuery\Clause\SeekClause;
use TinyBlocks\HttpQuery\Cursor\Criteria;
use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Schema;

final class SeekClauseTest extends TestCase
{
    public function testFromWhenNoIncomingCursorThenYieldsAnEmptyPredicate(): void
    {
        /** @Given a keyset over a sort with no incoming cursor */
        $keyset = Criteria::fromQuery(
            schema: Schema::create()->sortable(fields: ['id', 'created_at']),
            request: Query::from(parameters: ['sort' => '-created_at,id', 'page' => ['size' => '2']])
        )->keyset();

        /** @When the seek predicate is assembled */
        $seek = SeekClause::from(
            keyset: $keyset,
            columns: FilterColumns::create()
                ->wrapped(field: 'id', column: 'pay.id', binding: 'UUID_TO_BIN(%s)')
                ->plain(field: 'created_at', column: 'pay.created_at')
        );

        /** @Then the predicate and its parameters are empty */
        self::assertTrue($seek->isEmpty());
        self::assertSame('', $seek->sql());
        self::assertSame([], $seek->parameters());
    }

    public function testFromWhenMultipleOrdersThenRendersTheRowValueComparison(): void
    {
        /** @Given an opaque cursor anchored on a creation timestamp and an identifier */
        $token = Token::fromKeys(keys: ['2026-01-15T10:30:00Z', '01900000-0000-7099-8000-000000000001'])->toString();

        /** @And a keyset descending by creation time then ascending by identifier following the cursor */
        $keyset = Criteria::fromQuery(
            schema: Schema::create()->sortable(fields: ['id', 'created_at']),
            request: Query::from(
                parameters: ['sort' => '-created_at,id', 'page' => ['cursor' => $token, 'size' => '2']]
            )
        )->keyset();

        /** @When the seek predicate is assembled */
        $seek = SeekClause::from(
            keyset: $keyset,
            columns: FilterColumns::create()
                ->wrapped(field: 'id', column: 'pay.id', binding: 'UUID_TO_BIN(%s)')
                ->plain(field: 'created_at', column: 'pay.created_at')
        );

        /** @Then earlier keys are tied with equality while the next key is sought by its direction */
        $template = '(%s OR %s)';

        self::assertSame(
            sprintf(
                $template,
                '(pay.created_at < :seek_created_at)',
                '(pay.created_at = :seek_created_at AND pay.id > UUID_TO_BIN(:seek_id))'
            ),
            $seek->sql()
        );
        self::assertSame(
            ['seek_created_at' => '2026-01-15T10:30:00Z', 'seek_id' => '01900000-0000-7099-8000-000000000001'],
            $seek->parameters()
        );
    }

    public function testFromWhenSingleDescendingOrderThenSelectsRowsBelowTheCursor(): void
    {
        /** @Given an opaque cursor anchored on a creation timestamp */
        $token = Token::fromKeys(keys: ['2026-01-15T10:30:00Z'])->toString();

        /** @And a keyset descending by that single field following the cursor */
        $keyset = Criteria::fromQuery(
            schema: Schema::create()->sortable(fields: ['created_at']),
            request: Query::from(
                parameters: ['sort' => '-created_at', 'page' => ['cursor' => $token, 'size' => '2']]
            )
        )->keyset();

        /** @When the seek predicate is assembled */
        $seek = SeekClause::from(
            keyset: $keyset,
            columns: FilterColumns::create()->plain(field: 'created_at', column: 'pay.created_at')
        );

        /** @Then the predicate selects rows below the cursor on a single bound value */
        self::assertSame('((pay.created_at < :seek_created_at))', $seek->sql());
        self::assertSame(['seek_created_at' => '2026-01-15T10:30:00Z'], $seek->parameters());
    }
}
