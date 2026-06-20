<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Clause;

/**
 * Predicate or ordering fragment rendered as SQL with its bound parameters.
 */
interface SqlClause
{
    /**
     * Returns the rendered SQL fragment.
     *
     * @return string The rendered SQL fragment.
     */
    public function sql(): string;

    /**
     * Tells whether the fragment renders no SQL.
     *
     * @return bool True when the fragment is empty.
     */
    public function isEmpty(): bool;

    /**
     * Returns the values bound to the fragment placeholders.
     *
     * @return array<string, mixed> The values bound to the fragment placeholders.
     */
    public function parameters(): array;
}
