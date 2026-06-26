<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a sort expression cannot be parsed into the ordering.
 *
 * Each field is comma-separated and may carry a leading minus for descending order, so an empty
 * field or a field that carries reserved characters fails to parse.
 */
final class SortExpressionIsInvalid extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON_TEMPLATE = 'Sort expression <%s> is invalid and could not be parsed.';

    private function __construct(string $expression)
    {
        $template = SortExpressionIsInvalid::REASON_TEMPLATE;

        parent::__construct(message: sprintf($template, $expression));
    }

    /**
     * Creates a SortExpressionIsInvalid from the offending expression.
     *
     * @param string $expression The sort expression that failed to parse.
     * @return SortExpressionIsInvalid The composed exception describing the invalid expression.
     */
    public static function from(string $expression): SortExpressionIsInvalid
    {
        return new SortExpressionIsInvalid(expression: $expression);
    }
}
