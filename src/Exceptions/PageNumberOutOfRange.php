<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a requested page number falls outside the valid range.
 *
 * The page number is one-based, so it must be greater than or equal to 1.
 */
final class PageNumberOutOfRange extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON_TEMPLATE = 'Page number <%d> must be greater than or equal to 1.';

    private function __construct(int $page)
    {
        $template = PageNumberOutOfRange::REASON_TEMPLATE;

        parent::__construct(message: sprintf($template, $page));
    }

    /**
     * Creates a PageNumberOutOfRange from the offending page number.
     *
     * @param int $page The page number that fell outside the valid range.
     * @return PageNumberOutOfRange The composed exception describing the invalid page number.
     */
    public static function from(int $page): PageNumberOutOfRange
    {
        return new PageNumberOutOfRange(page: $page);
    }
}
