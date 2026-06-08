<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\Http\Server\Response;
use TinyBlocks\HttpQuery\Exceptions\TotalIsNegative;
use TinyBlocks\HttpQuery\Internal\Limit;
use TinyBlocks\HttpQuery\Internal\Offset\Offset;
use TinyBlocks\HttpQuery\Internal\Offset\OffsetNavigator;
use TinyBlocks\HttpQuery\Internal\Offset\PageCount;
use TinyBlocks\HttpQuery\Internal\Offset\PageNumber;
use TinyBlocks\HttpQuery\Internal\Offset\Total;

/**
 * Offset-based page carrying its items, the total element count, and the navigation targets.
 *
 * @template TValue
 */
final readonly class OffsetPage
{
    /**
     * @param Collection<TValue> $items
     */
    private function __construct(
        private Collection $items,
        private Total $total,
        private Criteria $criteria,
        private OffsetNavigator $navigator,
        private PageCount $pageCount,
        private PageNumber $pageNumber,
        private OffsetPagination $pagination
    ) {
    }

    /**
     * Creates an OffsetPage from the items, the total element count, the criteria, and the pagination.
     *
     * @template TElement
     * @param iterable<TElement> $items The items on the current page.
     * @param int $total The total element count across every page.
     * @param Criteria $criteria The criteria that produced the result.
     * @param OffsetPagination $pagination The pagination describing the current page.
     * @return OffsetPage<TElement> The page carrying the items, the total, and the navigation.
     * @throws TotalIsNegative If the total is less than 0.
     */
    public static function from(
        iterable $items,
        int $total,
        Criteria $criteria,
        OffsetPagination $pagination
    ): OffsetPage {
        $count = Total::from(value: $total);
        $limit = Limit::from(value: $pagination->limit());
        $offset = Offset::from(value: $pagination->offset());

        /** @var Collection<TElement> $collection */
        $collection = Collection::createFrom(elements: [...$items]);

        return new OffsetPage(
            items: $collection,
            total: $count,
            criteria: $criteria,
            navigator: OffsetNavigator::from(pagination: $pagination),
            pageCount: $count->pageCount(limit: $limit),
            pageNumber: PageNumber::fromOffset(offset: $offset, limit: $limit),
            pagination: $pagination
        );
    }

    /**
     * Returns the pagination for the last page, or null when there is no page.
     *
     * @return OffsetPagination|null The pagination for the last page, or null.
     */
    public function last(): ?OffsetPagination
    {
        return $this->pageCount->isEmpty() ? null : $this->navigator->atPage(page: $this->pageCount->value());
    }

    /**
     * Returns the pagination for the next page, or null when there is none.
     *
     * @return OffsetPagination|null The pagination for the next page, or null.
     */
    public function next(): ?OffsetPagination
    {
        return $this->hasNext() ? $this->navigator->next() : null;
    }

    /**
     * Returns the pagination for the first page, or null when there is no page.
     *
     * @return OffsetPagination|null The pagination for the first page, or null.
     */
    public function first(): ?OffsetPagination
    {
        return $this->pageCount->isEmpty() ? null : $this->navigator->first();
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
     * Tells whether the current page is the last page.
     *
     * @return bool True when the current page is the last page.
     */
    public function isLast(): bool
    {
        return !$this->hasNext();
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
        return $this->pageCount->hasPageAfter(page: $this->pageNumber);
    }

    /**
     * Tells whether the current page is the first page.
     *
     * @return bool True when the current page is the first page.
     */
    public function isFirst(): bool
    {
        return $this->navigator->isFirst();
    }

    /**
     * Returns the page as the JSON:API meta contents.
     *
     * @return array<string, int|bool> The meta contents keyed in length-ascending order.
     */
    public function metadata(): array
    {
        return [
            'total'        => $this->total->value(),
            'has_next'     => $this->hasNext(),
            'per_page'     => $this->pagination->limit(),
            'total_pages'  => $this->pageCount->value(),
            'current_page' => $this->pageNumber->value(),
            'has_previous' => $this->hasPrevious()
        ];
    }

    /**
     * Returns the pagination for the previous page, or null when there is none.
     *
     * @return OffsetPagination|null The pagination for the previous page, or null.
     */
    public function previous(): ?OffsetPagination
    {
        return $this->navigator->previous();
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
        $links = Links::from(baseUri: $baseUri, criteria: $this->criteria, navigation: $this->navigation());

        return Response::ok([
            'data'  => $this->items->toArray(),
            'meta'  => $this->metadata(),
            'links' => $links->toArray()
        ], $links->toHeader());
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
        return $this->pageNumber->value();
    }

    /**
     * Tells whether a previous page exists.
     *
     * @return bool True when a previous page exists.
     */
    public function hasPrevious(): bool
    {
        return !$this->navigator->isFirst();
    }
}
