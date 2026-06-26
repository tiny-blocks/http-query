<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Models;

use TinyBlocks\HttpQuery\Clause\SqlClause;

final readonly class ConstantSqlClause implements SqlClause
{
    /**
     * @param string $sql The rendered SQL fragment.
     * @param array<string, mixed> $parameters The values bound to the fragment placeholders.
     */
    private function __construct(private string $sql, private array $parameters)
    {
    }

    /**
     * @param string $sql The rendered SQL fragment.
     * @param array<string, mixed> $parameters The values bound to the fragment placeholders.
     * @return ConstantSqlClause The constant clause.
     */
    public static function from(string $sql, array $parameters): ConstantSqlClause
    {
        return new ConstantSqlClause(sql: $sql, parameters: $parameters);
    }

    public function sql(): string
    {
        return $this->sql;
    }

    public function isEmpty(): bool
    {
        return $this->sql === '';
    }

    public function parameters(): array
    {
        return $this->parameters;
    }
}
