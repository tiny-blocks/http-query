<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Internal\Iso8601;

/**
 * Kind a filter value or a cursor value is validated against, backed by its canonical token.
 *
 * <p>A <code>UUID</code> is the canonical hyphenated form of a UUID in either letter case, a
 * <code>STRING</code> is any non-empty string, an <code>INTEGER</code> is an optionally signed
 * sequence of digits, and a <code>DATETIME</code> is an ISO-8601 date or date-time.</p>
 */
enum ValueKind: string
{
    case UUID = 'uuid';
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
            ValueKind::UUID     => preg_match('/^[\da-f]{8}(-[\da-f]{4}){3}-[\da-f]{12}$/i', $value) === 1,
            ValueKind::STRING   => $value !== '',
            ValueKind::INTEGER  => preg_match('/^-?\d+$/', $value) === 1,
            ValueKind::DATETIME => Iso8601::isValid(value: $value)
        };
    }
}
