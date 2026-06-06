<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Direction applied to a single sort field, backed by its canonical token.
 */
enum Direction: string
{
    case ASCENDING = 'asc';
    case DESCENDING = 'desc';

    /**
     * Returns the prefix marking the direction in a sort expression.
     *
     * @return string An empty string when ascending, a leading minus when descending.
     */
    public function prefix(): string
    {
        return match ($this) {
            Direction::ASCENDING  => '',
            Direction::DESCENDING => '-'
        };
    }
}
