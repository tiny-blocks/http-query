<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

final class AnchoredPrefix
{
    public const string ESCAPE = '!';

    private const array WILDCARDS = [AnchoredPrefix::ESCAPE, '%', '_'];
    private const array NEUTRALIZED = ['!!', '!%', '!_'];

    private function __construct()
    {
    }

    public static function from(string $value): string
    {
        $escaped = str_replace(AnchoredPrefix::WILDCARDS, AnchoredPrefix::NEUTRALIZED, $value);
        $template = '%s%%';

        return sprintf($template, $escaped);
    }
}
