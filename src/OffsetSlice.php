<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\Http\Server\Response;
use TinyBlocks\HttpQuery\Internal\Limit;
use TinyBlocks\HttpQuery\Internal\Offset\Offset;
use TinyBlocks\HttpQuery\Internal\Offset\OffsetNavigator;
use TinyBlocks\HttpQuery\Internal\Offset\PageNumber;
use TinyBlocks\HttpQuery\Internal\Window;

/**
 * Offset-based slice carrying its items and the next-page hint, without a total element count.
 *
 * @template TValue
 */
final readonly class OffsetSlice
{
    /**
     * @param Collection<TValue> $items
     */
    private function __construct(
        private Collection $items,
        private bool $hasNext,
        private Criteria $criteria,
        private OffsetNavigator $navigator,
        private PageNumber $pageNumber,
        private OffsetPagination $pagination
    ) {
    }

    /**
     * Creates an OffsetSlice from the items, the criteria, and the pagination.
     *
     * <p>The consumer fetches one element beyond the page size. The extra element is trimmed and
     * its presence is read as the next-page hint.</p>
     *
     * @template TElement
     * @param iterable<TElement> $items The items fetched for the page size plus one.
     * @param Criteria $criteria The criteria that produced the result.
     * @param OffsetPagination $pagination The pagination describing the current page.
     * @return OffsetSlice<TElement> The slice carrying the trimmed items and the next-page hint.
     */
    public static function from(iterable $items, Criteria $criteria, OffsetPagination $pagination): OffsetSlice
    {
        $limit = Limit::from(value: $pagination->limit());
        $offset = Offset::from(value: $pagination->offset());

        /** @var Collection<TElement> $collection */
        $collection = Collection::createFrom(elements: $items);
        $window = Window::from(items: $collection, limit: $pagination->limit());

        /** @var Collection<TElement> $trimmed */
        $trimmed = $window->items();

        return new OffsetSlice(
            items: $trimmed,
            hasNext: $window->hasNext(),
            criteria: $criteria,
            navigator: OffsetNavigator::from(pagination: $pagination),
            pageNumber: PageNumber::fromOffset(offset: $offset, limit: $limit),
            pagination: $pagination
        );
    }

    /**
     * Returns the pagination for the next page, or null when there is none.
     *
     * @return OffsetPagination|null The pagination for the next page, or null.
     */
    public function next(): ?OffsetPagination
    {
        return $this->hasNext ? $this->navigator->next() : null;
    }

    /**
     * Returns the pagination for the first page.
     *
     * @return OffsetPagination The pagination for the first page.
     */
    public function first(): OffsetPagination
    {
        return $this->navigator->first();
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
        $links = Links::from(baseUri: $baseUri, criteria: $this->criteria, navigation: $this->navigation());

        return Response::ok([
            'data'  => $this->items->toArray(),
            'meta'  => $this->metadata(),
            'links' => $links->toArray()
        ], $links->toHeader());
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
