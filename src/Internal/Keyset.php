<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use Closure;
use TinyBlocks\Collection\Collection;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\CursorPagination;

/**
 * @template TValue
 */
final readonly class Keyset
{
    /**
     * @param Closure(TValue): list<mixed> $keysOf
     * @param Window<TValue> $window
     */
    private function __construct(private Cursor $cursor, private Closure $keysOf, private Window $window)
    {
    }

    /**
     * @template TElement
     * @param Collection<TElement> $items
     * @param Closure(TElement): list<mixed> $keysOf
     * @return Keyset<TElement>
     */
    public static function from(Collection $items, Closure $keysOf, CursorPagination $pagination): Keyset
    {
        /** @var Window<TElement> $window */
        $window = Window::from(items: $items, limit: $pagination->limit());

        return new Keyset(cursor: $pagination->cursor(), keysOf: $keysOf, window: $window);
    }

    public function next(): Cursor
    {
        return $this->cursorFor(element: $this->hasNext() ? $this->window->items()->last() : null);
    }

    /**
     * @return Collection<TValue>
     */
    public function items(): Collection
    {
        return $this->window->items();
    }

    public function hasNext(): bool
    {
        return $this->window->hasNext();
    }

    public function previous(): Cursor
    {
        return $this->cursorFor(element: $this->hasPrevious() ? $this->window->items()->first() : null);
    }

    private function cursorFor(mixed $element): Cursor
    {
        return is_null($element) ? Cursor::none() : Cursor::fromKeys(keys: ($this->keysOf)($element));
    }

    public function hasPrevious(): bool
    {
        return !$this->cursor->isAbsent();
    }
}
