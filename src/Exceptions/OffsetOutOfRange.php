<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a requested offset falls outside the valid range.
 *
 * The offset is zero-based, so it must be greater than or equal to 0.
 */
final class OffsetOutOfRange extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON_TEMPLATE = 'Offset <%d> must be greater than or equal to 0.';

    private function __construct(int $offset)
    {
        $template = OffsetOutOfRange::REASON_TEMPLATE;

        parent::__construct(message: sprintf($template, $offset));
    }

    /**
     * Creates an OffsetOutOfRange from the offending offset.
     *
     * @param int $offset The offset that fell outside the valid range.
     * @return OffsetOutOfRange The composed exception describing the invalid offset.
     */
    public static function from(int $offset): OffsetOutOfRange
    {
        return new OffsetOutOfRange(offset: $offset);
    }
}
