<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;
use TinyBlocks\HttpQuery\Operator;

/**
 * Raised when an incoming filter uses an operator not declared for the field.
 *
 * The consumer declares the operators each field accepts on the schema. A comparison whose
 * operator falls outside that set is rejected.
 */
final class FilterOperatorNotAllowed extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON_TEMPLATE = 'Operator <%s> is not allowed for filter field <%s>.';

    private function __construct(string $field, Operator $operator)
    {
        $template = FilterOperatorNotAllowed::REASON_TEMPLATE;

        parent::__construct(message: sprintf($template, $operator->value, $field));
    }

    /**
     * Creates a FilterOperatorNotAllowed from the field and its disallowed operator.
     *
     * @param string $field The field whose operator was rejected.
     * @param Operator $operator The operator that was never declared for the field.
     * @return FilterOperatorNotAllowed The composed exception describing the disallowed operator.
     */
    public static function from(string $field, Operator $operator): FilterOperatorNotAllowed
    {
        return new FilterOperatorNotAllowed(field: $field, operator: $operator);
    }
}
