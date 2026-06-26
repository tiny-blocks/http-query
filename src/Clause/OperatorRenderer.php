<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Clause;

use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Operator;

/**
 * Rendering of one comparison operator into a SQL predicate fragment.
 */
interface OperatorRenderer
{
    /**
     * Renders the comparison against the column into a predicate fragment.
     *
     * @param FilterColumn $column The column the comparison field maps to.
     * @param int $offset The number of placeholders already bound by earlier fragments.
     * @param Comparison $comparison The comparison to render against the column.
     * @return Fragment The predicate fragment rendered for the comparison.
     */
    public function render(FilterColumn $column, int $offset, Comparison $comparison): Fragment;

    /**
     * Tells whether the renderer handles the given operator.
     *
     * @param Operator $operator The operator to render.
     * @return bool True when the renderer handles the operator.
     */
    public function supports(Operator $operator): bool;
}
