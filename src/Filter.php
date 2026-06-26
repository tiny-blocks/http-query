<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Node of the immutable filter tree parsed from an RSQL expression.
 *
 * <p>A node is either a {@see Comparison} leaf or a {@see Group} composite. The marker is sealed
 * by the two implementations the library ships, so the parser builds the tree and the criteria
 * validates it into the conjunction of comparisons the consumer reads.</p>
 */
interface Filter
{
    /**
     * Tells whether the filter carries no constraint.
     *
     * @return bool True when the filter is empty.
     */
    public function isEmpty(): bool;
}
