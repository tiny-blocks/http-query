<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when an RSQL filter expression cannot be parsed into the filter tree.
 *
 * The expression violates the FIQL/RSQL grammar, for example an unknown operator, an unclosed
 * group or quote, a missing selector, or trailing characters after a complete expression. The message
 * never carries the expression, since its compared values may hold personal data.
 */
final class FilterExpressionIsInvalid extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON = 'Filter expression is invalid and could not be parsed.';

    private function __construct()
    {
        parent::__construct(message: FilterExpressionIsInvalid::REASON);
    }

    /**
     * Creates a FilterExpressionIsInvalid from the offending expression.
     *
     * @param string $expression The RSQL filter expression that failed to parse, kept out of the message.
     * @return FilterExpressionIsInvalid The composed exception describing the invalid expression.
     */
    public static function from(string $expression): FilterExpressionIsInvalid
    {
        return new FilterExpressionIsInvalid();
    }
}
