<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;
use TinyBlocks\HttpQuery\ValueKind;

/**
 * Raised when an incoming filter compares a value the field does not accept.
 *
 * A value is rejected when it falls outside the permitted set declared for the field, or when it
 * does not match the value kind the field expects.
 */
final class FilterValueNotAllowed extends InvalidArgumentException implements HttpQueryException
{
    private const string KIND_MISMATCH = 'Value <%s> does not match the %s kind for filter field <%s>.';
    private const string NOT_PERMITTED = 'Value <%s> is not permitted for filter field <%s>.';

    private function __construct(string $reason)
    {
        parent::__construct(message: $reason);
    }

    /**
     * Creates a FilterValueNotAllowed signaling that the value does not match the expected kind.
     *
     * @param ValueKind $kind The value kind the field expects.
     * @param string $field The field whose value was rejected.
     * @param string $value The value that does not match the kind.
     * @return FilterValueNotAllowed The composed exception describing the kind mismatch.
     */
    public static function kindMismatch(ValueKind $kind, string $field, string $value): FilterValueNotAllowed
    {
        $template = FilterValueNotAllowed::KIND_MISMATCH;

        return new FilterValueNotAllowed(reason: sprintf($template, $value, $kind->value, $field));
    }

    /**
     * Creates a FilterValueNotAllowed signaling that the value falls outside the permitted set.
     *
     * @param string $field The field whose value was rejected.
     * @param string $value The value that falls outside the permitted set.
     * @return FilterValueNotAllowed The composed exception describing the disallowed value.
     */
    public static function notPermitted(string $field, string $value): FilterValueNotAllowed
    {
        $template = FilterValueNotAllowed::NOT_PERMITTED;

        return new FilterValueNotAllowed(reason: sprintf($template, $value, $field));
    }
}
