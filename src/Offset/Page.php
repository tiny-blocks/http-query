<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Offset;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Exceptions\TotalIsNegative;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Internal\Limit;
use TinyBlocks\HttpQuery\Internal\Offset\Offset;
use TinyBlocks\HttpQuery\Internal\Offset\OffsetNavigation;
use TinyBlocks\HttpQuery\Internal\Offset\PageCount;
use TinyBlocks\HttpQuery\Internal\Offset\PageNumber;
use TinyBlocks\HttpQuery\Internal\Offset\Total;
use TinyBlocks\HttpQuery\Internal\Rendering;
use TinyBlocks\HttpQuery\Navigation;
use TinyBlocks\HttpQuery\Sort;

/**
 * Offset-based page carrying its items, the total element count, and the navigation targets.
 *
 * @template TValue
 */
final readonly class Page
{
    /**
     * @param Collection<TValue> $items
     * @param array<string, mixed> $extraMetadata
     */
    private function __construct(
        private Sort $sort,
        private Collection $items,
        private Total $total,
        private Filter $filter,
        private OffsetNavigation $paging,
        private PageCount $pageCount,
        private Pagination $pagination,
        private array $extraMetadata
    ) {
    }

    /**
     * Creates a Page from the sort, the total element count, the items, the filter, and the pagination.
     *
     * @template TElement
     * @param Sort $sort The submitted sort preserved in every rendered URI.
     * @param int $total The total element count across every page.
     * @param iterable<TElement> $items The items on the current page.
     * @param Filter $filter The filter preserved in every rendered URI.
     * @param Pagination $pagination The pagination describing the current page.
     * @return Page<TElement> The page carrying the items, the total, and the navigation.
     * @throws TotalIsNegative If the total is less than 0.
     */
    public static function from(Sort $sort, iterable $items, int $total, Filter $filter, Pagination $pagination): Page
    {
        $count = Total::from(value: $total);
        $limit = Limit::from(value: $pagination->limit());
        $pageCount = $count->pageCount(limit: $limit);
        $pageNumber = PageNumber::fromOffset(limit: $limit, offset: Offset::from(value: $pagination->offset()));

        /** @var Collection<TElement> $collection */
        $collection = Collection::createFrom(elements: [...$items]);

        return new Page(
            sort: $sort,
            items: $collection,
            total: $count,
            filter: $filter,
            paging: OffsetNavigation::from(
                hasNext: $pageCount->hasPageAfter(page: $pageNumber),
                pagination: $pagination
            ),
            pageCount: $pageCount,
            pagination: $pagination,
            extraMetadata: []
        );
    }

    /**
     * Returns the pagination for the last page, or null when there is no page.
     *
     * @return Pagination|null The pagination for the last page, or null.
     */
    public function last(): ?Pagination
    {
        return $this->pageCount->isEmpty() ? null : $this->paging->atPage(page: $this->pageCount->value());
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
     * Returns the pagination for the first page, or null when there is no page.
     *
     * @return Pagination|null The pagination for the first page, or null.
     */
    public function first(): ?Pagination
    {
        return $this->pageCount->isEmpty() ? null : $this->paging->first();
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
        return $this->total->value();
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
     * Returns the page as the JSON:API meta contents.
     *
     * <p>The pagination contents come first. Any metadata supplied through withMetadata follows,
     * in the order it was given, and a supplied key that repeats a pagination one is discarded.</p>
     *
     * @return array<string, mixed> The meta contents, the pagination counts and sizes first, then
     * the boolean flags, each by ascending key-name length, then the supplied metadata.
     */
    public function metadata(): array
    {
        $pagination = [
            'total'        => $this->total->value(),
            'per_page'     => $this->paging->limit(),
            'total_pages'  => $this->pageCount->value(),
            'current_page' => $this->paging->currentPage(),
            'has_next'     => $this->paging->hasNext(),
            'has_previous' => $this->paging->hasPrevious()
        ];

        return [...$pagination, ...array_diff_key($this->extraMetadata, $pagination)];
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
     * Returns the page as a JSON:API response carrying the body and the RFC 8288 Link header.
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
     * Returns the total number of pages.
     *
     * @return int The total number of pages.
     */
    public function totalPages(): int
    {
        return $this->pageCount->value();
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

    /**
     * Returns a copy of the page carrying the supplied metadata in its meta contents.
     *
     * <p>The supplied metadata is the place for a counter the consumer owns and the page cannot
     * derive, an unread total for example. It renders inside meta, so the response keeps the
     * single JSON:API envelope and the RFC 8288 Link header.</p>
     *
     * @param array<string, mixed> $metadata The metadata added to the meta contents.
     * @return Page<TValue> A copy carrying the supplied metadata, preserving the items and the navigation.
     */
    public function withMetadata(array $metadata): Page
    {
        return new Page(
            sort: $this->sort,
            items: $this->items,
            total: $this->total,
            filter: $this->filter,
            paging: $this->paging,
            pageCount: $this->pageCount,
            pagination: $this->pagination,
            extraMetadata: [...$this->extraMetadata, ...$metadata]
        );
    }
}
