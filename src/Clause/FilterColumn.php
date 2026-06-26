<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Clause;

use Closure;

/**
 * Column a filterable field maps to, with the binding and the normalization it applies to a value.
 */
final readonly class FilterColumn
{
    /**
     * @param string $column The column the field maps to.
     * @param string $binding The template wrapping the placeholder bound to the column.
     * @param Closure(string): (string|int) $normalizer The transformation applied to each raw value.
     */
    private function __construct(private string $column, private string $binding, private Closure $normalizer)
    {
    }

    /**
     * Creates a column that binds the value as is.
     *
     * @param string $column The column the field maps to.
     * @return FilterColumn The column binding the value without transformation.
     */
    public static function plain(string $column): FilterColumn
    {
        return new FilterColumn(
            column: $column,
            binding: '%s',
            normalizer: static fn(string $value): string => $value
        );
    }

    /**
     * Creates a column that binds the value as a boolean flag.
     *
     * @param string $column The column the field maps to.
     * @return FilterColumn The column binding the value as 1 when true, otherwise 0.
     */
    public static function boolean(string $column): FilterColumn
    {
        return new FilterColumn(
            column: $column,
            binding: '%s',
            normalizer: static fn(string $value): int => $value === 'true' ? 1 : 0
        );
    }

    /**
     * Creates a column whose placeholder is wrapped by the given binding.
     *
     * @param string $column The column the field maps to.
     * @param string $binding The template wrapping the placeholder (for example UUID_TO_BIN(%s)).
     * @return FilterColumn The column binding the value through the wrapping template.
     */
    public static function wrapped(string $column, string $binding): FilterColumn
    {
        return new FilterColumn(
            column: $column,
            binding: $binding,
            normalizer: static fn(string $value): string => $value
        );
    }

    /**
     * Returns the column the field maps to.
     *
     * @return string The column the field maps to.
     */
    public function column(): string
    {
        return $this->column;
    }

    /**
     * Returns the template wrapping the placeholder bound to the column.
     *
     * @return string The template wrapping the placeholder bound to the column.
     */
    public function binding(): string
    {
        return $this->binding;
    }

    /**
     * Normalizes the value to the form bound to the placeholder.
     *
     * @param string $value The raw value carried by the comparison.
     * @return string|int The value in the form bound to the placeholder.
     */
    public function normalize(string $value): string|int
    {
        return ($this->normalizer)($value);
    }
}
