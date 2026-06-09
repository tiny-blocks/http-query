<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use TinyBlocks\Collection\Collection;

/**
 * @template TValue
 */
final readonly class Window
{
    /**
     * @param Collection<TValue> $items
     */
    private function __construct(private Collection $items, private bool $hasNext)
    {
    }

    /**
     * @param Collection<TValue> $items
     * @return Window<TValue>
     */
    public static function from(int $limit, Collection $items): Window
    {
        $elements = [...$items];
        $hasNext = count($elements) > $limit;

        /** @var Collection<TValue> $trimmed */
        $trimmed = Collection::createFrom(elements: array_slice($elements, 0, $limit));

        return new Window(items: $trimmed, hasNext: $hasNext);
    }

    /**
     * @return Collection<TValue>
     */
    public function items(): Collection
    {
        return $this->items;
    }

    public function hasNext(): bool
    {
        return $this->hasNext;
    }
}
