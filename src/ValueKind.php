<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Internal\DecimalInteger;
use TinyBlocks\HttpQuery\Internal\Iso8601;

/**
 * Kind a filter value or a cursor value is validated against, backed by its canonical token.
 *
 * <p>A <code>UUID</code> is the canonical hyphenated form of a UUID in either letter case, a
 * <code>STRING</code> is any non-empty string, an <code>INTEGER</code> is an optionally signed
 * sequence of digits that fits in an integer, and a <code>DATETIME</code> is an ISO-8601 date or
 * date-time that exists in the calendar, whose instant in UTC falls between years 0001 and 9999.</p>
 *
 * <p>No kind accepts a trailing line break. A date-time carries an hour from 00 to 23, a minute and
 * a second from 00 to 59, an optional fraction of up to nine digits, and an optional <code>Z</code>
 * or <code>±HH:MM</code> offset whose hours run from 00 to 23 and whose minutes run from 00 to 59.</p>
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
            ValueKind::UUID     => preg_match('/^[\da-f]{8}(-[\da-f]{4}){3}-[\da-f]{12}$/Di', $value) === 1,
            ValueKind::STRING   => $value !== '',
            ValueKind::INTEGER  => DecimalInteger::isValid(value: $value),
            ValueKind::DATETIME => Iso8601::isValid(value: $value)
        };
    }
}
