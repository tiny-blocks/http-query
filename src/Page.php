<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Exceptions\TotalIsNegative;

/**
 * Offset-based page carrying its items, the total element count, and the navigation targets.
 *
 * @template TValue
 */
final readonly class Page
{
    /**
     * @param Collection<TValue> $items
     */
    private function __construct(private Collection $items, private int $total, private Pagination $pagination)
    {
    }

    /**
     * Creates a Page from the items, the total element count, and the pagination.
     *
     * @param Collection<TValue> $items The items on the current page.
     * @param int $total The total element count across every page.
     * @param Pagination $pagination The pagination describing the current page.
     * @return Page<TValue> The page carrying the items, the total, and the navigation.
     * @throws TotalIsNegative If the total is less than 0.
     */
    public static function from(Collection $items, int $total, Pagination $pagination): Page
    {
        if ($total < 0) {
            throw TotalIsNegative::from(total: $total);
        }

        return new Page(items: $items, total: $total, pagination: $pagination);
    }

    /**
     * Returns the pagination for the last page, or null when there is no page.
     *
     * @return Pagination|null The pagination for the last page, or null.
     */
    public function last(): ?Pagination
    {
        return $this->totalPages() === 0 ? null : $this->pagination->atPage(page: $this->totalPages());
    }

    /**
     * Returns the pagination for the next page, or null when there is none.
     *
     * @return Pagination|null The pagination for the next page, or null.
     */
    public function next(): ?Pagination
    {
        return $this->hasNext() ? $this->pagination->next() : null;
    }

    /**
     * Returns the pagination for the first page, or null when there is no page.
     *
     * @return Pagination|null The pagination for the first page, or null.
     */
    public function first(): ?Pagination
    {
        return $this->totalPages() === 0 ? null : $this->pagination->first();
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
     * Returns the total element count across every page.
     *
     * @return int The total element count.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Tells whether the current page is the last page.
     *
     * @return bool True when the current page is the last page.
     */
    public function isLast(): bool
    {
        return $this->currentPage() >= $this->totalPages();
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
        return $this->currentPage() < $this->totalPages();
    }

    /**
     * Tells whether the current page is the first page.
     *
     * @return bool True when the current page is the first page.
     */
    public function isFirst(): bool
    {
        return !$this->pagination->hasPrevious();
    }

    /**
     * Returns the page as the JSON:API meta contents.
     *
     * @return array<string, int|bool> The meta contents keyed in length-ascending order.
     */
    public function metadata(): array
    {
        return [
            'total'        => $this->total,
            'has_next'     => $this->hasNext(),
            'per_page'     => $this->pagination->limit(),
            'total_pages'  => $this->totalPages(),
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
     * Returns the navigation exposing the first, previous, next, and last targets.
     *
     * @return Navigation The navigation listing the surrounding pages present for this page.
     */
    public function navigation(): Navigation
    {
        return Navigation::empty()
            ->with(target: $this->first(), relation: LinkRelation::FIRST)
            ->with(target: $this->previous(), relation: LinkRelation::PREVIOUS)
            ->with(target: $this->next(), relation: LinkRelation::NEXT)
            ->with(target: $this->last(), relation: LinkRelation::LAST);
    }

    /**
     * Returns the total number of pages.
     *
     * @return int The total number of pages.
     */
    public function totalPages(): int
    {
        return (int)ceil($this->total / $this->pagination->limit());
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
