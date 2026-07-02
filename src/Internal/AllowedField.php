<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\FilterOperatorNotAllowed;
use TinyBlocks\HttpQuery\Exceptions\FilterValueNotAllowed;
use TinyBlocks\HttpQuery\ValueKind;

final readonly class AllowedField
{
    private function __construct(
        private ?ValueKind $kind,
        private string $field,
        private ?array $values,
        private array $operators
    ) {
    }

    public static function from(?ValueKind $kind, string $field, ?array $values, array $operators): AllowedField
    {
        return new AllowedField(kind: $kind, field: $field, values: $values, operators: $operators);
    }

    public function permit(Comparison $comparison): Comparison
    {
        if (!in_array($comparison->operator(), $this->operators, true)) {
            throw FilterOperatorNotAllowed::from(field: $this->field, operator: $comparison->operator());
        }

        foreach ($comparison->values() as $value) {
            $this->permitValue(value: $value);
        }

        return $comparison;
    }

    public function hasField(string $field): bool
    {
        return $this->field === $field;
    }

    private function permitValue(string $value): void
    {
        if (!is_null($this->values) && !in_array($value, $this->values, true)) {
            throw FilterValueNotAllowed::notPermitted(field: $this->field, value: $value);
        }

        if (!is_null($this->kind) && !$this->kind->matches(value: $value)) {
            throw FilterValueNotAllowed::kindMismatch(kind: $this->kind, field: $this->field, value: $value);
        }
    }
}
