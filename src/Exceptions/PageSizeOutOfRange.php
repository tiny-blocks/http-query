<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;
use TinyBlocks\HttpQuery\Schema;

/**
 * Raised when a requested page size falls outside the valid range.
 *
 * The page size must be greater than or equal to 1 and less than or equal to the maximum allowed
 * by the {@see Schema}.
 */
final class PageSizeOutOfRange extends InvalidArgumentException implements HttpQueryException
{
    private const string ABOVE_MAXIMUM = 'Page size <%d> must be less than or equal to %d.';
    private const string BELOW_MINIMUM = 'Page size <%d> must be greater than or equal to 1.';

    private function __construct(string $reason)
    {
        parent::__construct(message: $reason);
    }

    /**
     * Creates a PageSizeOutOfRange signaling that the page size exceeds the allowed maximum.
     *
     * @param int $maximum The maximum page size allowed by the schema.
     * @param int $perPage The page size that exceeded the maximum.
     * @return PageSizeOutOfRange The composed exception describing the oversized page size.
     */
    public static function aboveMaximum(int $maximum, int $perPage): PageSizeOutOfRange
    {
        $template = PageSizeOutOfRange::ABOVE_MAXIMUM;

        return new PageSizeOutOfRange(reason: sprintf($template, $perPage, $maximum));
    }

    /**
     * Creates a PageSizeOutOfRange signaling that the page size is below the minimum of 1.
     *
     * @param int $perPage The page size that fell below the minimum.
     * @return PageSizeOutOfRange The composed exception describing the undersized page size.
     */
    public static function belowMinimum(int $perPage): PageSizeOutOfRange
    {
        $template = PageSizeOutOfRange::BELOW_MINIMUM;

        return new PageSizeOutOfRange(reason: sprintf($template, $perPage));
    }
}
