<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Node of the immutable filter tree parsed from an RSQL expression.
 *
 * <p>A node is either a {@see Comparison} leaf or a {@see Group} composite. The marker is sealed
 * by the two implementations the library ships, so consumers match on the concrete type when they
 * translate the tree to their own store.</p>
 */
interface Filter
{
    /**
     * Tells whether the filter carries no constraint.
     *
     * @return bool True when the filter is empty.
     */
    public function isEmpty(): bool;

    /**
     * Returns the filter as its RSQL expression.
     *
     * @return Expression The RSQL expression.
     */
    public function toExpression(): Expression;
}
