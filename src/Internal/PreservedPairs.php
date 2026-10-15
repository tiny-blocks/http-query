<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Exceptions\PreservedParameterIsInvalid;

final readonly class PreservedPairs
{
    private function __construct(private array $pairs)
    {
    }

    public static function from(array $parameters): PreservedPairs
    {
        $pairs = array_map(
            static function (int|string $name, mixed $value): array {
                $parameterName = ParameterName::from(value: (string)$name);

                return PreservedPairs::pairsOf(
                    key: rawurlencode($parameterName->value),
                    name: $parameterName,
                    value: $value
                );
            },
            array_keys($parameters),
            $parameters
        );

        return new PreservedPairs(pairs: array_merge(...$pairs));
    }

    private static function pairsOf(string $key, ParameterName $name, mixed $value): array
    {
        if (is_string($value)) {
            $template = '%s=%s';

            return [sprintf($template, $key, rawurlencode($value))];
        }

        if (!is_array($value)) {
            throw PreservedParameterIsInvalid::fromValue(name: $name->value);
        }

        $template = '%s[%s]';

        return array_merge(...array_map(
            static fn(int|string $index, mixed $nested): array => PreservedPairs::pairsOf(
                key: sprintf($template, $key, rawurlencode((string)$index)),
                name: $name,
                value: $nested
            ),
            array_keys($value),
            $value
        ));
    }

    public function toQueryString(): string
    {
        return implode('&', $this->pairs);
    }
}
