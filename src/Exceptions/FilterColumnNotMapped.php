<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use LogicException;

/**
 * Raised when a comparison targets an allowed field the consumer never mapped to a column.
 *
 * The consumer maps each filterable field to a column before assembling the predicate. A
 * comparison whose field has no column mapping cannot be rendered, and signals the missing mapping.
 */
final class FilterColumnNotMapped extends LogicException implements HttpQueryException
{
    private const string REASON_TEMPLATE = 'Filter field <%s> has no column mapping.';

    private function __construct(string $field)
    {
        $template = FilterColumnNotMapped::REASON_TEMPLATE;

        parent::__construct(message: sprintf($template, $field));
    }

    /**
     * Creates a FilterColumnNotMapped from the unmapped field.
     *
     * @param string $field The allowed field the consumer did not map to a column.
     * @return FilterColumnNotMapped The exception describing the unmapped field.
     */
    public static function from(string $field): FilterColumnNotMapped
    {
        return new FilterColumnNotMapped(field: $field);
    }
}
