<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when an incoming filter targets a field not declared through the filter rules.
 *
 * The consumer declares the filterable fields through the filter rules. A comparison whose field
 * was never allowed cannot be applied to the store and is rejected.
 */
final class FilterFieldNotAllowed extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON_TEMPLATE = 'Filter field <%s> is not allowed.';

    private function __construct(string $field)
    {
        $template = FilterFieldNotAllowed::REASON_TEMPLATE;

        parent::__construct(message: sprintf($template, $field));
    }

    /**
     * Creates a FilterFieldNotAllowed from the disallowed field.
     *
     * @param string $field The field that was never declared through the filter rules.
     * @return FilterFieldNotAllowed The composed exception describing the disallowed field.
     */
    public static function from(string $field): FilterFieldNotAllowed
    {
        return new FilterFieldNotAllowed(field: $field);
    }
}
