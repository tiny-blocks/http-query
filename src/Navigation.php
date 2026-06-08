<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;

/**
 * Ordered set of navigation targets a result exposes, each pairing a relation with its paging.
 *
 * <p>A result lists its own targets, so {@see Links} renders every result uniformly without
 * branching on the concrete result type. The insertion order is preserved.</p>
 */
final readonly class Navigation
{
    /**
     * @param Collection<NavigationTarget> $targets
     */
    private function __construct(private Collection $targets)
    {
    }

    /**
     * Creates an empty Navigation that carries no target.
     *
     * @return Navigation The empty navigation.
     */
    public static function empty(): Navigation
    {
        /** @var Collection<NavigationTarget> $targets */
        $targets = Collection::createFromEmpty();

        return new Navigation(targets: $targets);
    }

    /**
     * Returns a copy of the Navigation with the target added under the relation.
     *
     * <p>A null target is ignored, so the copy carries the same targets as the original.</p>
     *
     * @param Pagination|null $target The paging the target points to, or null to ignore.
     * @param LinkRelation $relation The relation the target is reached through.
     * @return Navigation A copy carrying the original targets plus the added target.
     */
    public function with(?Pagination $target, LinkRelation $relation): Navigation
    {
        if (is_null($target)) {
            return $this;
        }

        return new Navigation(targets: $this->targets->add(NavigationTarget::to(target: $target, relation: $relation)));
    }

    /**
     * Returns the navigation targets in insertion order.
     *
     * @return Collection<NavigationTarget> The navigation targets.
     */
    public function targets(): Collection
    {
        return $this->targets;
    }
}
