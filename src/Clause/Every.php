<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Clause;

/**
 * Conjunction of SQL clauses, joining the non-empty ones with AND.
 */
final readonly class Every implements SqlClause
{
    /**
     * @param array<SqlClause> $clauses The clauses combined with AND.
     */
    private function __construct(private array $clauses)
    {
    }

    /**
     * Combines the given clauses into a conjunction.
     *
     * @param SqlClause ...$clauses The clauses combined with AND.
     * @return Every The conjunction of the clauses.
     */
    public static function of(SqlClause ...$clauses): Every
    {
        return new Every(clauses: $clauses);
    }

    public function sql(): string
    {
        $present = array_filter($this->clauses, static fn(SqlClause $clause): bool => !$clause->isEmpty());

        return implode(' AND ', array_map(static fn(SqlClause $clause): string => $clause->sql(), $present));
    }

    public function isEmpty(): bool
    {
        return $this->sql() === '';
    }

    public function parameters(): array
    {
        return array_merge(
            [],
            ...array_map(static fn(SqlClause $clause): array => $clause->parameters(), $this->clauses)
        );
    }
}
