<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Cursor;

use Closure;
use TinyBlocks\Collection\Collection;
use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Internal\Window;

/**
 * @template TValue
 */
final readonly class Seek
{
    /**
     * @param Window<TValue> $window
     * @param Closure(TValue): list<mixed> $keysOf
     */
    private function __construct(private Window $window, private Closure $keysOf)
    {
    }

    /**
     * @template TElement
     * @param Collection<TElement> $items
     * @param Closure(TElement): list<mixed> $keysOf
     * @return Seek<TElement>
     */
    public static function from(int $limit, Collection $items, Closure $keysOf): Seek
    {
        /** @var Window<TElement> $window */
        $window = Window::from(limit: $limit, items: $items);

        return new Seek(window: $window, keysOf: $keysOf);
    }

    public function next(): Token
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

    private function cursorFor(mixed $element): Token
    {
        return is_null($element) ? Token::none() : Token::fromKeys(keys: ($this->keysOf)($element));
    }
}
