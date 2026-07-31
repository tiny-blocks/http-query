<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Comparison operator of a FIQL/RSQL filter, backed by its canonical token.
 *
 * @see https://github.com/jirutka/rsql-parser
 */
enum Operator: string
{
    case IN = '=in=';
    case EQUAL = '==';
    case NOT_IN = '=out=';
    case LESS_THAN = '=lt=';
    case NOT_EQUAL = '!=';
    case STARTS_WITH = '=sw=';
    case GREATER_THAN = '=gt=';
    case LESS_THAN_OR_EQUAL = '=le=';
    case GREATER_THAN_OR_EQUAL = '=ge=';

    /**
     * Tells whether the operator compares against a value list.
     *
     * @return bool True when the operator is IN or NOT_IN.
     */
    public function isMultiValued(): bool
    {
        return $this === Operator::IN || $this === Operator::NOT_IN;
    }
}
