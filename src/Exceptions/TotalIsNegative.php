<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a page is built with a negative total element count.
 *
 * The total counts the elements across every page, so it must be greater than or equal to 0.
 */
final class TotalIsNegative extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON_TEMPLATE = 'Total <%d> must be greater than or equal to 0.';

    private function __construct(int $total)
    {
        $template = TotalIsNegative::REASON_TEMPLATE;

        parent::__construct(message: sprintf($template, $total));
    }

    /**
     * Creates a TotalIsNegative from the offending total.
     *
     * @param int $total The total element count that fell below 0.
     * @return TotalIsNegative The composed exception describing the negative total.
     */
    public static function from(int $total): TotalIsNegative
    {
        return new TotalIsNegative(total: $total);
    }
}
