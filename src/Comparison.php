<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\Collection\Collection;

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
     * Returns the comparison operator.
     *
     * @return Operator The comparison operator.
     */
    public function operator(): Operator
    {
        return $this->operator;
    }

    public function toExpression(): Expression
    {
        /** @var Collection<string> $values */
        $values = Collection::createFrom(elements: $this->values);

        $arguments = $values
            ->map(transformations: static function (string $value): string {
                if ($value !== '' && preg_match('/[\s"\'();,=!~<>]/', $value) !== 1) {
                    return $value;
                }

                $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
                $template = '"%s"';

                return sprintf($template, $escaped);
            })
            ->joinToString(separator: ',');

        $wrapped = $this->operator->isMultiValued();
        $template = $wrapped ? '%s%s(%s)' : '%s%s%s';

        return Expression::atomic(value: sprintf($template, $this->field, $this->operator->value, $arguments));
    }
}
