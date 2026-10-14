<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a requested page number falls outside the valid range.
 *
 * The page number is one-based, so it must be greater than or equal to 1. Its zero-based offset must
 * also fit in an integer, so it must be less than or equal to the ceiling derived from the page size.
 */
final class PageNumberOutOfRange extends InvalidArgumentException implements HttpQueryException
{
    private const string ABOVE_MAXIMUM = 'Page number <%d> must be less than or equal to %d.';
    private const string BELOW_MINIMUM = 'Page number <%d> must be greater than or equal to 1.';

    private function __construct(string $reason)
    {
        parent::__construct(message: $reason);
    }

    /**
     * Creates a PageNumberOutOfRange from the offending page number.
     *
     * @param int $page The page number that fell below the minimum of 1.
     * @return PageNumberOutOfRange The composed exception describing the invalid page number.
     */
    public static function from(int $page): PageNumberOutOfRange
    {
        $template = PageNumberOutOfRange::BELOW_MINIMUM;

        return new PageNumberOutOfRange(reason: sprintf($template, $page));
    }

    /**
     * Creates a PageNumberOutOfRange signaling that the page number exceeds the ceiling for the page size.
     *
     * @param int $page The page number that exceeded the ceiling.
     * @param int $maximum The last page number whose offset fits in an integer for the page size.
     * @return PageNumberOutOfRange The composed exception describing the oversized page number.
     */
    public static function aboveMaximum(int $page, int $maximum): PageNumberOutOfRange
    {
        $template = PageNumberOutOfRange::ABOVE_MAXIMUM;

        return new PageNumberOutOfRange(reason: sprintf($template, $page, $maximum));
    }
}
