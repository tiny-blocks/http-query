<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Cursor;

use Closure;
use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Http\LinkRelation;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Internal\Cursor\Seek;
use TinyBlocks\HttpQuery\Internal\Rendering;
use TinyBlocks\HttpQuery\Navigation;
use TinyBlocks\HttpQuery\Sort;

/**
 * Forward-only keyset (cursor) page carrying its items and the next cursor, without a total.
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
        private Filter $filter,
        private bool $hasNext,
        private Token $nextCursor,
        private Pagination $pagination,
        private array $extraMetadata
    ) {
    }

    /**
     * Creates a Page from the sort, the items, the filter, the key extractor, and the pagination.
     *
     * <p>The consumer fetches one element beyond the page size via keyset. The extra element is
     * trimmed and its presence is read as the next-page hint. The next cursor anchors on the last
     * retained element.</p>
     *
     * @template TElement
     * @param Sort $sort The submitted sort preserved in every rendered URI.
     * @param iterable<TElement> $items The items fetched for the page size plus one.
     * @param Filter $filter The filter preserved in every rendered URI.
     * @param Closure(TElement): list<mixed> $keysOf Extracts the ordering key values from an element.
     * @param Pagination $pagination The pagination describing the current page.
     * @return Page<TElement> The cursor page carrying the trimmed items and the next cursor.
     */
    public static function from(
        Sort $sort,
        iterable $items,
        Filter $filter,
        Closure $keysOf,
        Pagination $pagination
    ): Page {
        /** @var Collection<TElement> $collection */
        $collection = Collection::createFrom(elements: $items);
        $seek = Seek::from(items: $collection, limit: $pagination->limit(), keysOf: $keysOf);

        return new Page(
            sort: $sort,
            items: $seek->items(),
            filter: $filter,
            hasNext: $seek->hasNext(),
            nextCursor: $seek->next(),
            pagination: $pagination,
            extraMetadata: []
        );
    }

    /**
     * Returns a copy of the cursor page with the items mapped through the transformation.
     *
     * @template TNew
     * @param Closure(TValue): TNew $transformation The transformation applied to each item.
     * @return Page<TNew> A copy carrying the mapped items, preserving the cursor and the page size.
     */
    public function map(Closure $transformation): Page
    {
        /** @var Collection<TNew> $items */
        $items = $this->items->map(transformations: $transformation);

        return new Page(
            sort: $this->sort,
            items: $items,
            filter: $this->filter,
            hasNext: $this->hasNext,
            nextCursor: $this->nextCursor,
            pagination: $this->pagination,
            extraMetadata: $this->extraMetadata
        );
    }

    /**
     * Returns the pagination for the next page, or null when there is none.
     *
     * @return Pagination|null The pagination for the next page, or null.
     */
    public function next(): ?Pagination
    {
        return $this->nextCursor->isAbsent()
            ? null
            : Pagination::from(cursor: $this->nextCursor, perPage: $this->pagination->limit());
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
     * <p>Any metadata supplied through withMetadata comes first, in the order it was given. The
     * pagination contents come last, so a supplied key never shadows them.</p>
     *
     * @return array<string, mixed> The meta contents, the supplied metadata first, then the
     * pagination counts and sizes, then the boolean flags, each by ascending key-name length.
     */
    public function metadata(): array
    {
        return [
            ...$this->extraMetadata,
            'per_page' => $this->pagination->limit(),
            'has_next' => $this->hasNext
        ];
    }

    /**
     * Returns the navigation exposing only the next target.
     *
     * @return Navigation The navigation listing the next page when present.
     */
    public function navigation(): Navigation
    {
        return Navigation::empty()->with(target: $this->next(), relation: LinkRelation::NEXT);
    }

    /**
     * Returns the cursor page as a JSON:API response carrying the body and the RFC 8288 Link header.
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
     * Returns a copy of the cursor page carrying the supplied metadata in its meta contents.
     *
     * <p>The supplied metadata is the place for a counter the consumer owns and the page cannot
     * derive, an unread total for example. It renders inside meta, so the response keeps the
     * single JSON:API envelope and the RFC 8288 Link header.</p>
     *
     * @param array<string, mixed> $metadata The metadata added to the meta contents.
     * @return Page<TValue> A copy carrying the supplied metadata, preserving the items and the cursor.
     */
    public function withMetadata(array $metadata): Page
    {
        return new Page(
            sort: $this->sort,
            items: $this->items,
            filter: $this->filter,
            hasNext: $this->hasNext,
            nextCursor: $this->nextCursor,
            pagination: $this->pagination,
            extraMetadata: [...$this->extraMetadata, ...$metadata]
        );
    }
}
