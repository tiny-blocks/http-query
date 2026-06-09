<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Internal\Iso8601;

/**
 * Kind a filter value is validated against, backed by its canonical token.
 *
 * <p>A <code>STRING</code> is any non-empty string, an <code>INTEGER</code> is an optionally
 * signed sequence of digits, and a <code>DATETIME</code> is an ISO-8601 date or date-time.</p>
 */
enum ValueKind: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case DATETIME = 'datetime';

    /**
     * Tells whether the value matches this kind.
     *
     * @param string $value The value to test against the kind.
     * @return bool True when the value matches the kind.
     */
    public function matches(string $value): bool
    {
        return match ($this) {
            ValueKind::STRING   => $value !== '',
            ValueKind::INTEGER  => preg_match('/^-?\d+$/', $value) === 1,
            ValueKind::DATETIME => Iso8601::isValid(value: $value)
        };
    }
}
