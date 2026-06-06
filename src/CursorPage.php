<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use Closure;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Internal\Window;

/**
 * Keyset (cursor) page carrying its items and the forward and backward cursors, without a total.
 *
 * @template TValue
 */
final readonly class CursorPage
{
    /**
     * @param Collection<TValue> $items
     */
    private function __construct(
        private Collection $items,
        private bool $hasNext,
        private int $perPage,
        private Cursor $nextCursor,
        private bool $hasPrevious,
        private Cursor $previousCursor
    ) {
    }

    /**
     * Creates a CursorPage from the items, the key extractor, and the pagination.
     *
     * <p>The consumer fetches one element beyond the page size via keyset. The extra element is
     * trimmed and its presence is read as the next-page hint. The forward cursor anchors on the
     * last retained element, the backward cursor on the first.</p>
     *
     * @param Collection<TValue> $items The items fetched for the page size plus one.
     * @param Closure(TValue): list<mixed> $keysOf Extracts the ordering key values from an element.
     * @param CursorPagination $pagination The pagination describing the current page.
     * @return CursorPage<TValue> The cursor page carrying the trimmed items and the cursors.
     */
    public static function from(Collection $items, Closure $keysOf, CursorPagination $pagination): CursorPage
    {
        $limit = $pagination->limit();
        $window = Window::from(items: $items, limit: $limit);
        $hasNext = $window->hasNext();
        $hasPrevious = !$pagination->cursor()->isAbsent();

        /** @var Collection<TValue> $trimmed */
        $trimmed = $window->items();

        $lastItem = $hasNext ? $trimmed->last() : null;
        $firstItem = $hasPrevious ? $trimmed->first() : null;

        $nextCursor = is_null($lastItem) ? Cursor::none() : Cursor::fromKeys(keys: $keysOf($lastItem));
        $previousCursor = is_null($firstItem) ? Cursor::none() : Cursor::fromKeys(keys: $keysOf($firstItem));

        return new CursorPage(
            items: $trimmed,
            hasNext: $hasNext,
            perPage: $limit,
            nextCursor: $nextCursor,
            hasPrevious: $hasPrevious,
            previousCursor: $previousCursor
        );
    }

    /**
     * Returns the items on the current page.
     *
     * @return Collection<TValue> The items on the current page.
     */
    public function items(): Collection
    {
        return $this->items;
    }

    /**
     * Tells whether a next page exists.
     *
     * @return bool True when a next page exists.
     */
    public function hasNext(): bool
    {
        return $this->hasNext;
    }

    /**
     * Returns the cursor page as the JSON:API meta contents.
     *
     * @return array<string, int|string|bool|null> The meta contents keyed in length-ascending order.
     */
    public function metadata(): array
    {
        return [
            'has_next'        => $this->hasNext,
            'per_page'        => $this->perPage,
            'next_cursor'     => $this->nextCursor->isAbsent() ? null : $this->nextCursor->toString(),
            'has_previous'    => $this->hasPrevious,
            'previous_cursor' => $this->previousCursor->isAbsent() ? null : $this->previousCursor->toString()
        ];
    }

    /**
     * Returns the navigation exposing the previous and next targets.
     *
     * @return Navigation The navigation listing the keyset pages present for this page.
     */
    public function navigation(): Navigation
    {
        $next = $this->nextCursor->isAbsent()
            ? null
            : CursorPagination::from(cursor: $this->nextCursor, perPage: $this->perPage);
        $previous = $this->previousCursor->isAbsent()
            ? null
            : CursorPagination::from(cursor: $this->previousCursor, perPage: $this->perPage);

        return Navigation::empty()
            ->with(target: $previous, relation: LinkRelation::PREVIOUS)
            ->with(target: $next, relation: LinkRelation::NEXT);
    }

    /**
     * Returns the forward cursor, absent when there is no next page.
     *
     * @return Cursor The forward cursor.
     */
    public function nextCursor(): Cursor
    {
        return $this->nextCursor;
    }

    /**
     * Tells whether a previous page exists.
     *
     * @return bool True when a previous page exists.
     */
    public function hasPrevious(): bool
    {
        return $this->hasPrevious;
    }

    /**
     * Returns the backward cursor, absent when there is no previous page.
     *
     * @return Cursor The backward cursor.
     */
    public function previousCursor(): Cursor
    {
        return $this->previousCursor;
    }
}
