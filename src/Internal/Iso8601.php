<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use DateTimeImmutable;
use DateTimeZone;

final class Iso8601
{
    private const string PATTERN = '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})'
        . '(?:T(?:[01]\d|2[0-3])(?::[0-5]\d){2}(?:\.\d{1,9})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)?)?$/D';
    private const string LATEST_INSTANT = '9999-12-31T23:59:59.999999Z';
    private const string EARLIEST_INSTANT = '0001-01-01T00:00:00Z';

    private function __construct()
    {
    }

    public static function isValid(string $value): bool
    {
        if (
            preg_match(Iso8601::PATTERN, $value, $date) !== 1
            || !checkdate((int)$date['month'], (int)$date['day'], (int)$date['year'])
        ) {
            return false;
        }

        $instant = new DateTimeImmutable(datetime: $value, timezone: new DateTimeZone(timezone: 'UTC'));

        return $instant >= new DateTimeImmutable(datetime: Iso8601::EARLIEST_INSTANT)
            && $instant <= new DateTimeImmutable(datetime: Iso8601::LATEST_INSTANT);
    }
}
