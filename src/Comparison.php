<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Leaf of the filter tree pairing a field with an operator and the values compared against it.
 */
final readonly class Comparison implements Filter
{
    private function __construct(private string $field, private array $values, private Operator $operator)
    {
    }

    /**
     * Creates a Comparison from a field, its compared values, and the operator.
     *
     * @param string $field The field being compared.
     * @param list<string> $values The compared values, a single element except for IN and NOT_IN.
     * @param Operator $operator The comparison operator.
     * @return Comparison The composed comparison leaf.
     */
    public static function of(string $field, array $values, Operator $operator): Comparison
    {
        return new Comparison(field: $field, values: $values, operator: $operator);
    }

    /**
     * Returns the field being compared.
     *
     * @return string The field being compared.
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * Returns the values compared against the field.
     *
     * @return list<string> The compared values, a single element except for IN and NOT_IN.
     */
    public function values(): array
    {
        return $this->values;
    }

    public function isEmpty(): bool
    {
        return false;
    }

    /**
     * Tells whether the comparison targets the given field.
     *
     * @param string $field The field to compare against.
     * @return bool True when the comparison targets the field.
     */
    public function hasField(string $field): bool
    {
        return $this->field === $field;
    }

    /**
     * Returns the comparison operator.
     *
     * @return Operator The comparison operator.
     */
    public function operator(): Operator
    {
        return $this->operator;
    }

    /**
     * Returns the first compared value.
     *
     * @return string The first of the values compared against the field.
     */
    public function firstValue(): string
    {
        return $this->values[0];
    }

    /**
     * Tells whether the comparison carries the given operator.
     *
     * @param Operator $operator The operator to compare against.
     * @return bool True when the comparison carries the operator.
     */
    public function hasOperator(Operator $operator): bool
    {
        return $this->operator === $operator;
    }
}
