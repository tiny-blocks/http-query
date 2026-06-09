<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

final class Iso8601
{
    public static function isValid(string $value): bool
    {
        $pattern = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})?)?$/';

        return preg_match($pattern, $value) === 1;
    }
}
