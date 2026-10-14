<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a requested offset falls outside the valid range.
 *
 * The offset is zero-based, so it must be greater than or equal to 0. The one-based page number
 * derived from it must also fit in an integer, so it must be less than the largest integer.
 */
final class OffsetOutOfRange extends InvalidArgumentException implements HttpQueryException
{
    private const string ABOVE_MAXIMUM = 'Offset <%d> must be less than or equal to %d.';
    private const string BELOW_MINIMUM = 'Offset <%d> must be greater than or equal to 0.';

    private function __construct(string $reason)
    {
        parent::__construct(message: $reason);
    }

    /**
     * Creates an OffsetOutOfRange from the offending offset.
     *
     * @param int $offset The offset that fell below the minimum of 0.
     * @return OffsetOutOfRange The composed exception describing the invalid offset.
     */
    public static function from(int $offset): OffsetOutOfRange
    {
        $template = OffsetOutOfRange::BELOW_MINIMUM;

        return new OffsetOutOfRange(reason: sprintf($template, $offset));
    }

    /**
     * Creates an OffsetOutOfRange signaling that the offset exceeds the largest offset accepted.
     *
     * @param int $offset The offset that exceeded the maximum.
     * @param int $maximum The largest offset whose one-based page number fits in an integer.
     * @return OffsetOutOfRange The composed exception describing the oversized offset.
     */
    public static function aboveMaximum(int $offset, int $maximum): OffsetOutOfRange
    {
        $template = OffsetOutOfRange::ABOVE_MAXIMUM;

        return new OffsetOutOfRange(reason: sprintf($template, $offset, $maximum));
    }
}
