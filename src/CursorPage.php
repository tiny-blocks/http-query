<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use Closure;
use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\Http\Server\Response;
use TinyBlocks\HttpQuery\Internal\Cursor\Keyset;
use TinyBlocks\HttpQuery\Internal\Limit;

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
        private Limit $limit,
        private bool $hasNext,
        private Criteria $criteria,
        private Cursor $nextCursor,
        private bool $hasPrevious,
        private Cursor $previousCursor
    ) {
    }

    /**
     * Creates a CursorPage from the items, the key extractor, the criteria, and the pagination.
     *
     * <p>The consumer fetches one element beyond the page size via keyset. The extra element is
     * trimmed and its presence is read as the next-page hint. The forward cursor anchors on the
     * last retained element, the backward cursor on the first.</p>
     *
     * @template TElement
     * @param iterable<TElement> $items The items fetched for the page size plus one.
     * @param Closure(TElement): list<mixed> $keysOf Extracts the ordering key values from an element.
     * @param Criteria $criteria The criteria that produced the result.
     * @param CursorPagination $pagination The pagination describing the current page.
     * @return CursorPage<TElement> The cursor page carrying the trimmed items and the cursors.
     */
    public static function from(
        iterable $items,
        Closure $keysOf,
        Criteria $criteria,
        CursorPagination $pagination
    ): CursorPage {
        /** @var Collection<TElement> $collection */
        $collection = Collection::createFrom(elements: $items);
        $keyset = Keyset::from(items: $collection, keysOf: $keysOf, pagination: $pagination);

        return new CursorPage(
            items: $keyset->items(),
            limit: Limit::from(value: $pagination->limit()),
            hasNext: $keyset->hasNext(),
            criteria: $criteria,
            nextCursor: $keyset->next(),
            hasPrevious: $keyset->hasPrevious(),
            previousCursor: $keyset->previous()
        );
    }

    /**
     * Returns the pagination for the next page, or null when there is none.
     *
     * @return CursorPagination|null The pagination for the next page, or null.
     */
    public function next(): ?CursorPagination
    {
        return $this->nextCursor->isAbsent()
            ? null
            : CursorPagination::from(cursor: $this->nextCursor, perPage: $this->limit->value());
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
     * @return array<string, int|bool> The meta contents keyed in length-ascending order.
     */
    public function metadata(): array
    {
        return [
            'has_next'     => $this->hasNext,
            'per_page'     => $this->limit->value(),
            'has_previous' => $this->hasPrevious
        ];
    }

    /**
     * Returns the pagination for the previous page, or null when there is none.
     *
     * @return CursorPagination|null The pagination for the previous page, or null.
     */
    public function previous(): ?CursorPagination
    {
        return $this->previousCursor->isAbsent()
            ? null
            : CursorPagination::from(cursor: $this->previousCursor, perPage: $this->limit->value());
    }

    /**
     * Returns the navigation exposing the previous and next targets.
     *
     * @return Navigation The navigation listing the keyset pages present for this page.
     */
    public function navigation(): Navigation
    {
        return Navigation::empty()
            ->with(target: $this->previous(), relation: LinkRelation::PREVIOUS)
            ->with(target: $this->next(), relation: LinkRelation::NEXT);
    }

    /**
     * Returns the cursor page as a JSON:API response carrying the body and the RFC 8288 Link header.
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
     * Tells whether a previous page exists.
     *
     * @return bool True when a previous page exists.
     */
    public function hasPrevious(): bool
    {
        return $this->hasPrevious;
    }
}
