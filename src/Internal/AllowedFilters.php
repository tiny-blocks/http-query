<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\Collection\Collection;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\FilterFieldNotAllowed;
use TinyBlocks\HttpQuery\ValueKind;

final readonly class AllowedFilters
{
    /**
     * @param Collection<AllowedField> $fields
     */
    private function __construct(private Collection $fields)
    {
    }

    public static function createFromEmpty(): AllowedFilters
    {
        /** @var Collection<AllowedField> $fields */
        $fields = Collection::createFromEmpty();

        return new AllowedFilters(fields: $fields);
    }

    public function with(?ValueKind $kind, string $field, ?array $values, array $operators): AllowedFilters
    {
        $rule = AllowedField::from(kind: $kind, field: $field, values: $values, operators: $operators);

        return new AllowedFilters(fields: $this->fields->add($rule));
    }

    public function permit(Comparison $comparison): Comparison
    {
        $rule = $this->fields->findBy(
            predicates: static fn(AllowedField $allowed): bool => $allowed->hasField(field: $comparison->field())
        );

        if (is_null($rule)) {
            throw FilterFieldNotAllowed::from(field: $comparison->field());
        }

        return $rule->permit(comparison: $comparison);
    }
}
