<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Internal\Window;

/**
 * Offset-based slice carrying its items and the next-page hint, without a total element count.
 *
 * @template TValue
 */
final readonly class Slice
{
    /**
     * @param Collection<TValue> $items
     */
    private function __construct(private Collection $items, private bool $hasNext, private Pagination $pagination)
    {
    }

    /**
     * Creates a Slice from the items and the pagination.
     *
     * <p>The consumer fetches one element beyond the page size. The extra element is trimmed and
     * its presence is read as the next-page hint.</p>
     *
     * @param Collection<TValue> $items The items fetched for the page size plus one.
     * @param Pagination $pagination The pagination describing the current page.
     * @return Slice<TValue> The slice carrying the trimmed items and the next-page hint.
     */
    public static function from(Collection $items, Pagination $pagination): Slice
    {
        $window = Window::from(items: $items, limit: $pagination->limit());

        /** @var Collection<TValue> $trimmed */
        $trimmed = $window->items();

        return new Slice(items: $trimmed, hasNext: $window->hasNext(), pagination: $pagination);
    }

    /**
     * Returns the pagination for the next page, or null when there is none.
     *
     * @return Pagination|null The pagination for the next page, or null.
     */
    public function next(): ?Pagination
    {
        return $this->hasNext ? $this->pagination->next() : null;
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
     * Returns the zero-based offset of the current page.
     *
     * @return int The offset of the current page.
     */
    public function offset(): int
    {
        return $this->pagination->offset();
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
     * Returns the slice as the JSON:API meta contents.
     *
     * @return array<string, int|bool> The meta contents keyed in length-ascending order.
     */
    public function metadata(): array
    {
        return [
            'has_next'     => $this->hasNext,
            'per_page'     => $this->pagination->limit(),
            'current_page' => $this->currentPage(),
            'has_previous' => $this->hasPrevious()
        ];
    }

    /**
     * Returns the pagination for the previous page, or null when there is none.
     *
     * @return Pagination|null The pagination for the previous page, or null.
     */
    public function previous(): ?Pagination
    {
        return $this->pagination->previous();
    }

    /**
     * Returns the navigation exposing the previous and next targets.
     *
     * @return Navigation The navigation listing the surrounding pages present for this slice.
     */
    public function navigation(): Navigation
    {
        return Navigation::empty()
            ->with(target: $this->previous(), relation: LinkRelation::PREVIOUS)
            ->with(target: $this->next(), relation: LinkRelation::NEXT);
    }

    /**
     * Returns the one-based current page number.
     *
     * @return int The current page number.
     */
    public function currentPage(): int
    {
        return $this->pagination->page();
    }

    /**
     * Tells whether a previous page exists.
     *
     * @return bool True when a previous page exists.
     */
    public function hasPrevious(): bool
    {
        return $this->pagination->hasPrevious();
    }
}
