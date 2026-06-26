<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Offset;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Internal\Offset\OffsetNavigation;
use TinyBlocks\HttpQuery\Internal\Rendering;
use TinyBlocks\HttpQuery\Internal\Window;
use TinyBlocks\HttpQuery\Navigation;
use TinyBlocks\HttpQuery\Sort;

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
    private function __construct(
        private Sort $sort,
        private Collection $items,
        private Filter $filter,
        private OffsetNavigation $paging,
        private Pagination $pagination
    ) {
    }

    /**
     * Creates a Slice from the sort, the items, the filter, and the pagination.
     *
     * <p>The consumer fetches one element beyond the page size. The extra element is trimmed and
     * its presence is read as the next-page hint.</p>
     *
     * @template TElement
     * @param Sort $sort The submitted sort preserved in every rendered URI.
     * @param iterable<TElement> $items The items fetched for the page size plus one.
     * @param Filter $filter The filter preserved in every rendered URI.
     * @param Pagination $pagination The pagination describing the current page.
     * @return Slice<TElement> The slice carrying the trimmed items and the next-page hint.
     */
    public static function from(Sort $sort, iterable $items, Filter $filter, Pagination $pagination): Slice
    {
        /** @var Collection<TElement> $collection */
        $collection = Collection::createFrom(elements: $items);
        $window = Window::from(items: $collection, limit: $pagination->limit());

        /** @var Collection<TElement> $trimmed */
        $trimmed = $window->items();

        return new Slice(
            sort: $sort,
            items: $trimmed,
            filter: $filter,
            paging: OffsetNavigation::from(hasNext: $window->hasNext(), pagination: $pagination),
            pagination: $pagination
        );
    }

    /**
     * Returns the pagination for the next page, or null when there is none.
     *
     * @return Pagination|null The pagination for the next page, or null.
     */
    public function next(): ?Pagination
    {
        return $this->paging->next();
    }

    /**
     * Returns the pagination for the first page.
     *
     * @return Pagination The pagination for the first page.
     */
    public function first(): Pagination
    {
        return $this->paging->first();
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
        return $this->paging->offset();
    }

    /**
     * Tells whether a next page exists.
     *
     * @return bool True when a next page exists.
     */
    public function hasNext(): bool
    {
        return $this->paging->hasNext();
    }

    /**
     * Returns the slice as the JSON:API meta contents.
     *
     * @return array<string, int|bool> The meta contents, counts and sizes first, then the boolean
     * flags, each by ascending key-name length.
     */
    public function metadata(): array
    {
        return [
            'per_page'     => $this->paging->limit(),
            'current_page' => $this->paging->currentPage(),
            'has_next'     => $this->paging->hasNext(),
            'has_previous' => $this->paging->hasPrevious()
        ];
    }

    /**
     * Returns the pagination for the previous page, or null when there is none.
     *
     * @return Pagination|null The pagination for the previous page, or null.
     */
    public function previous(): ?Pagination
    {
        return $this->paging->previous();
    }

    /**
     * Returns the navigation exposing the first, previous, and next targets.
     *
     * @return Navigation The navigation listing the surrounding pages present for this slice.
     */
    public function navigation(): Navigation
    {
        return Navigation::empty()
            ->with(target: $this->first(), relation: LinkRelation::FIRST)
            ->with(target: $this->previous(), relation: LinkRelation::PREVIOUS)
            ->with(target: $this->next(), relation: LinkRelation::NEXT);
    }

    /**
     * Returns the slice as a JSON:API response carrying the body and the RFC 8288 Link header.
     *
     * @param string $baseUri The base URI the navigation URIs are built on.
     * @return ResponseInterface The response with the data, meta, and links, plus the Link header.
     */
    public function toResponse(string $baseUri): ResponseInterface
    {
        return Rendering::of(
            self: $this->pagination,
            sort: $this->sort,
            items: $this->items,
            filter: $this->filter,
            baseUri: $baseUri,
            metadata: $this->metadata(),
            navigation: $this->navigation()
        );
    }

    /**
     * Returns the one-based current page number.
     *
     * @return int The current page number.
     */
    public function currentPage(): int
    {
        return $this->paging->currentPage();
    }

    /**
     * Tells whether a previous page exists.
     *
     * @return bool True when a previous page exists.
     */
    public function hasPrevious(): bool
    {
        return $this->paging->hasPrevious();
    }
}
