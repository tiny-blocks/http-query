<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

final class DecimalInteger
{
    private function __construct()
    {
    }

    public static function isValid(string $value): bool
    {
        if (preg_match('/^(?<sign>-?)0*(?<digits>\d+)$/D', $value, $parts) !== 1) {
            return false;
        }

        $bound = ltrim((string)($parts['sign'] === '-' ? PHP_INT_MIN : PHP_INT_MAX), '-');
        $digits = str_pad($parts['digits'], strlen($bound), '0', STR_PAD_LEFT);

        return strlen($digits) === strlen($bound) && strcmp($digits, $bound) <= 0;
    }
}
