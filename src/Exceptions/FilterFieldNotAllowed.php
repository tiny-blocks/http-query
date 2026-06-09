<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when an incoming filter targets a field not declared filterable on the schema.
 *
 * The consumer declares the filterable fields on the schema. A comparison whose field was never
 * allowed cannot be applied to the store and is rejected.
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
     * @param string $field The field the schema did not declare filterable.
     * @return FilterFieldNotAllowed The composed exception describing the disallowed field.
     */
    public static function from(string $field): FilterFieldNotAllowed
    {
        return new FilterFieldNotAllowed(field: $field);
    }
}
