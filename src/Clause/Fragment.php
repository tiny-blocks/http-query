<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Clause;

/**
 * Predicate fragment rendered for a single comparison, with its bound parameters.
 */
final readonly class Fragment
{
    /**
     * @param string $sql The rendered fragment.
     * @param array<string, mixed> $parameters The values bound to the fragment placeholders.
     */
    private function __construct(private string $sql, private array $parameters)
    {
    }

    /**
     * Creates a Fragment from its rendered SQL and its bound parameters.
     *
     * @param string $sql The rendered fragment.
     * @param array<string, mixed> $parameters The values bound to the fragment placeholders.
     * @return Fragment The rendered fragment.
     */
    public static function of(string $sql, array $parameters): Fragment
    {
        return new Fragment(sql: $sql, parameters: $parameters);
    }

    /**
     * Returns the rendered SQL fragment.
     *
     * @return string The rendered SQL fragment.
     */
    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * Returns the values bound to the fragment placeholders.
     *
     * @return array<string, mixed> The values bound to the fragment placeholders.
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
