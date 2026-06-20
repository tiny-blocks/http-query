<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Clause;

use TinyBlocks\HttpQuery\Exceptions\FilterColumnNotMapped;

/**
 * Mapping from each queryable field to the column it resolves to.
 */
final readonly class FilterColumns
{
    /**
     * @param array<string, FilterColumn> $columns The column each field maps to.
     */
    private function __construct(private array $columns)
    {
    }

    /**
     * Creates an empty mapping.
     *
     * @return FilterColumns The empty mapping.
     */
    public static function create(): FilterColumns
    {
        return new FilterColumns(columns: []);
    }

    /**
     * Returns the column the field maps to.
     *
     * @param string $field The mapped field.
     * @return FilterColumn The column the field maps to.
     * @throws FilterColumnNotMapped When the field has no column mapping.
     */
    public function for(string $field): FilterColumn
    {
        if (!array_key_exists($field, $this->columns)) {
            throw FilterColumnNotMapped::from(field: $field);
        }

        return $this->columns[$field];
    }

    /**
     * Tells whether the field has a column mapping.
     *
     * @param string $field The field to look up.
     * @return bool True when the field has a column mapping.
     */
    public function has(string $field): bool
    {
        return array_key_exists($field, $this->columns);
    }

    /**
     * Returns a mapping extended with the field mapped to the column.
     *
     * @param string $field The mapped field.
     * @param FilterColumn $column The column the field maps to.
     * @return FilterColumns The extended mapping.
     */
    public function with(string $field, FilterColumn $column): FilterColumns
    {
        return new FilterColumns(columns: [...$this->columns, $field => $column]);
    }

    /**
     * Returns a mapping extended with the field mapped to a plain column.
     *
     * @param string $field The mapped field.
     * @param string $column The column the field maps to.
     * @return FilterColumns The extended mapping.
     */
    public function plain(string $field, string $column): FilterColumns
    {
        return $this->with(field: $field, column: FilterColumn::plain(column: $column));
    }

    /**
     * Returns a mapping extended with the field mapped to a boolean column.
     *
     * @param string $field The mapped field.
     * @param string $column The column the field maps to.
     * @return FilterColumns The extended mapping.
     */
    public function boolean(string $field, string $column): FilterColumns
    {
        return $this->with(field: $field, column: FilterColumn::boolean(column: $column));
    }

    /**
     * Returns a mapping extended with the field mapped to a column wrapping its placeholder.
     *
     * @param string $field The mapped field.
     * @param string $column The column the field maps to.
     * @param string $binding The binding template wrapping the placeholder.
     * @return FilterColumns The extended mapping.
     */
    public function wrapped(string $field, string $column, string $binding): FilterColumns
    {
        return $this->with(field: $field, column: FilterColumn::wrapped(column: $column, binding: $binding));
    }
}
