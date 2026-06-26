<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * Page size of a paginated query, with the arithmetic a fetch performs over it.
 */
final readonly class Limit
{
    private function __construct(private int $size)
    {
    }

    /**
     * Creates a Limit from a page size.
     *
     * @param int $size The page size.
     * @return Limit The page size value.
     */
    public static function of(int $size): Limit
    {
        return new Limit(size: $size);
    }

    /**
     * Returns a Limit raised by the given amount.
     *
     * @param int $extra The amount added to the page size.
     * @return Limit The raised page size.
     */
    public function plus(int $extra): Limit
    {
        return new Limit(size: $this->size + $extra);
    }

    /**
     * Returns a Limit raised by one, the extra row a page fetches to detect a next page.
     *
     * @return Limit The page size raised by one.
     */
    public function plusOne(): Limit
    {
        return $this->plus(extra: 1);
    }

    /**
     * Returns the page size as an integer.
     *
     * @return int The page size.
     */
    public function toInteger(): int
    {
        return $this->size;
    }
}
