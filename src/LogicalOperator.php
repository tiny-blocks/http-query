<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Logical connective of a FIQL/RSQL group, backed by its canonical token.
 *
 * <p>The AND token (<code>;</code>) binds tighter than the OR token (<code>,</code>).</p>
 *
 * @see https://github.com/jirutka/rsql-parser
 */
enum LogicalOperator: string
{
    case OR = ',';
    case AND = ';';

    /**
     * Tells whether this connective binds tighter than the given one.
     *
     * @param LogicalOperator $other The connective to compare against.
     * @return bool True when this connective binds tighter.
     */
    public function bindsTighterThan(LogicalOperator $other): bool
    {
        return $this === LogicalOperator::AND && $other === LogicalOperator::OR;
    }
}
