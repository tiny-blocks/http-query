<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when an incoming filter is not a comparison or a flat AND group of comparisons.
 *
 * The supported shape is a conjunction of comparisons. An OR group, or a group nested inside
 * another group, cannot be flattened into that shape and is rejected. The message never carries the
 * expression, since its compared values may hold personal data.
 */
final class FilterShapeNotSupported extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON = 'Filter shape is not supported.';

    private function __construct()
    {
        parent::__construct(message: FilterShapeNotSupported::REASON);
    }

    /**
     * Creates a FilterShapeNotSupported from the raw filter query string of the rejected filter.
     *
     * @param string $expression The raw filter query string whose shape is not supported, kept out of the message.
     * @return FilterShapeNotSupported The composed exception describing the unsupported shape.
     */
    public static function from(string $expression): FilterShapeNotSupported
    {
        return new FilterShapeNotSupported();
    }
}
