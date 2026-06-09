<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when an incoming sort orders by a field not declared through the sort rules.
 *
 * The consumer declares the sortable fields through the sort rules. A fixed rule allows none, so
 * any client-provided sort is rejected. An order by an undeclared field is likewise rejected.
 */
final class SortFieldNotAllowed extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON_TEMPLATE = 'Sort field <%s> is not allowed.';

    private function __construct(string $field)
    {
        $template = SortFieldNotAllowed::REASON_TEMPLATE;

        parent::__construct(message: sprintf($template, $field));
    }

    /**
     * Creates a SortFieldNotAllowed from the disallowed field.
     *
     * @param string $field The field that was never declared through the sort rules.
     * @return SortFieldNotAllowed The composed exception describing the disallowed field.
     */
    public static function from(string $field): SortFieldNotAllowed
    {
        return new SortFieldNotAllowed(field: $field);
    }
}
