<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a keyset is built without a deterministic order.
 *
 * A cursor page seeks past the last-seen ordering keys, so it needs a non-empty sort. The schema
 * must declare a default sort, or the client must sort, otherwise the keyset cannot anchor a page.
 */
final class SortIsRequired extends InvalidArgumentException implements HttpQueryException
{
    private const string REASON = 'A keyset requires a deterministic order, but the effective sort is empty.';

    public function __construct()
    {
        parent::__construct(message: SortIsRequired::REASON);
    }
}
