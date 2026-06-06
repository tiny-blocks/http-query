<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\Http\LinkRelation;

/**
 * Navigation target pairing a paging request with the link relation it is reached through.
 */
final readonly class NavigationTarget
{
    private function __construct(private Paging $target, private LinkRelation $relation)
    {
    }

    /**
     * Creates a NavigationTarget from a paging target and the relation it carries.
     *
     * @param Paging $target The paging the target points to.
     * @param LinkRelation $relation The relation the target is reached through.
     * @return NavigationTarget The composed navigation target.
     */
    public static function to(Paging $target, LinkRelation $relation): NavigationTarget
    {
        return new NavigationTarget(target: $target, relation: $relation);
    }

    /**
     * Returns the paging the target points to.
     *
     * @return Paging The paging target.
     */
    public function target(): Paging
    {
        return $this->target;
    }

    /**
     * Returns the relation the target is reached through.
     *
     * @return LinkRelation The link relation.
     */
    public function relation(): LinkRelation
    {
        return $this->relation;
    }
}
