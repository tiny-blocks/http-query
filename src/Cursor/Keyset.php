<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Cursor;

use Closure;
use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Internal\Cursor\SortKeys;
use TinyBlocks\HttpQuery\Limit;
use TinyBlocks\HttpQuery\Order;
use TinyBlocks\HttpQuery\Sort;

/**
 * Forward-only cursor view pairing the effective sort with the incoming cursor and the page size.
 *
 * <p>It exposes the seek inputs the consumer needs before fetching (the incoming cursor key values
 * keyed by sort field and the page size), and it builds the result page once the items are
 * fetched. The seek is ordered by the effective sort, while the submitted sort is the one preserved
 * in every rendered URI. It is produced only by {@see Criteria::keyset()}.</p>
 */
final readonly class Keyset
{
    private function __construct(
        private Sort $sort,
        private Filter $filter,
        private Pagination $pagination,
        private Sort $submittedSort
    ) {
    }

    /**
     * Creates a Keyset from the effective sort, the filter, the pagination, and the submitted sort.
     *
     * @param Sort $sort The effective sort the seek is ordered by.
     * @param Filter $filter The filter preserved in every rendered URI.
     * @param Pagination $pagination The cursor pagination carrying the incoming cursor and the limit.
     * @param Sort $submittedSort The submitted sort preserved in every rendered URI.
     * @return Keyset The cursor view.
     */
    public static function from(Sort $sort, Filter $filter, Pagination $pagination, Sort $submittedSort): Keyset
    {
        return new Keyset(sort: $sort, filter: $filter, pagination: $pagination, submittedSort: $submittedSort);
    }

    /**
     * Builds the cursor page from the items and an optional ordering-key extractor.
     *
     * <p>When the extractor is omitted, the ordering keys are read from the effective sort fields on
     * each array-shaped row, so the cursor keys come from the source rows.</p>
     *
     * @template TElement
     * @param iterable<TElement> $items The items fetched for the page size plus one.
     * @param Closure(TElement): list<mixed>|null $keysOf Extracts the ordering key values from an element.
     * @return Page<TElement> The cursor page carrying the trimmed items and the next cursor.
     */
    public function page(iterable $items, ?Closure $keysOf = null): Page
    {
        return Page::from(
            sort: $this->submittedSort,
            items: $items,
            filter: $this->filter,
            keysOf: $keysOf ?? SortKeys::from(sort: $this->sort),
            pagination: $this->pagination
        );
    }

    /**
     * Returns the page size.
     *
     * @return Limit The page size.
     */
    public function limit(): Limit
    {
        return Limit::of(size: $this->pagination->limit());
    }

    /**
     * Returns the incoming cursor key values keyed by sort field name.
     *
     * <p>Every effective sort field is present. The value is null when there is no incoming cursor,
     * that is on the first page.</p>
     *
     * @return array<string, mixed> The cursor key values keyed by sort field name.
     * @throws CursorIsInvalid If the decoded value count does not match the number of sort orders.
     */
    public function cursor(): array
    {
        $fields = array_map(static fn(Order $order): string => $order->field(), $this->sort->orders());

        return $this->pagination->cursor()->keyedBy(fields: $fields);
    }

    /**
     * Returns the effective sort orders.
     *
     * @return list<Order> The orders the seek is ordered by.
     */
    public function orders(): array
    {
        return $this->sort->orders();
    }
}
