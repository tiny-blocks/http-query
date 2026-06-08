<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Collection\KeyPreservation;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\Links;
use TinyBlocks\HttpQuery\OffsetPage;
use TinyBlocks\HttpQuery\OffsetPagination;

final class EndToEndTest extends TestCase
{
    public function testPipelineWhenRequestGivenThenOffsetPageRendersResponse(): void
    {
        /** @Given the canonical request query parameters */
        $query = Query::from(parameters: [
            'filter' => 'status==paid;total=ge=100',
            'sort'   => '-created_at,id',
            'page'   => ['number' => '3', 'size' => '20']
        ]);

        /** @And the criteria parsed from those parameters */
        $criteria = Criteria::fromQuery(request: $query);

        /** @And the base URI the navigation links render against */
        $base = '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id';

        /** @And the third page of a 480-element result built from the criteria */
        $page = $criteria->offsetPage(items: ['a', 'b'], total: 480);

        /** @When rendering the page as a JSON:API response over the orders base URI */
        $response = $page->toResponse(baseUri: '/v1/orders');

        /** @Then the body carries the data, the meta, and the navigation links preserving filter and sort */
        self::assertSame([
            'data'  => ['a', 'b'],
            'meta'  => [
                'total'        => 480,
                'has_next'     => true,
                'per_page'     => 20,
                'total_pages'  => 24,
                'current_page' => 3,
                'has_previous' => true
            ],
            'links' => [
                'self'  => sprintf('%s&page[number]=3&page[size]=20', $base),
                'first' => sprintf('%s&page[number]=1&page[size]=20', $base),
                'prev'  => sprintf('%s&page[number]=2&page[size]=20', $base),
                'next'  => sprintf('%s&page[number]=4&page[size]=20', $base),
                'last'  => sprintf('%s&page[number]=24&page[size]=20', $base)
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testPipelineWhenCursorRequestGivenThenCursorPageRendersResponse(): void
    {
        /** @Given an opaque token produced from ordering key values */
        $token = Cursor::fromKeys(keys: [5])->toString();

        /** @And query parameters carrying a sort, that cursor, and a page size of two */
        $query = Query::from(parameters: ['sort' => 'id', 'page' => ['cursor' => $token, 'size' => '2']]);

        /** @And the criteria parsed from those parameters */
        $criteria = Criteria::fromQuery(request: $query);

        /** @And a cursor page built from the criteria over items fetched for the page size plus one */
        $page = $criteria->cursorPage(items: [10, 20, 30], keysOf: static fn(mixed $element): array => [$element]);

        /** @And the base URI the keyset links render against */
        $base = '/v1/orders?sort=id';

        /** @When rendering the cursor page as a JSON:API response over the orders base URI */
        $response = $page->toResponse(baseUri: '/v1/orders');

        /** @Then the body carries the data, the meta, and the keyset links preserving the sort */
        self::assertSame([
            'data'  => [10, 20],
            'meta'  => [
                'has_next'     => true,
                'per_page'     => 2,
                'has_previous' => true
            ],
            'links' => [
                'self' => sprintf('%s&page[cursor]=%s&page[size]=2', $base, $token),
                'prev' => sprintf('%s&page[cursor]=%s&page[size]=2', $base, Cursor::fromKeys(keys: [10])->toString()),
                'next' => sprintf('%s&page[cursor]=%s&page[size]=2', $base, Cursor::fromKeys(keys: [20])->toString())
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testPipelineWhenCanonicalRequestGivenThenLinkHeaderFoldsEveryRelation(): void
    {
        /** @Given the canonical request query parameters */
        $query = Query::from(parameters: [
            'filter' => 'status==paid;total=ge=100',
            'sort'   => '-created_at,id',
            'page'   => ['number' => '3', 'size' => '20']
        ]);

        /** @And the criteria parsed from those parameters */
        $criteria = Criteria::fromQuery(request: $query);

        /** @And the offset pagination pointing at the third page */
        $pagination = OffsetPagination::fromPage(page: 3, perPage: 20);

        /** @And the third page of a 480-element result */
        $page = OffsetPage::from(
            items: Collection::createFromEmpty(),
            total: 480,
            criteria: $criteria,
            pagination: $pagination
        );

        /** @And the navigation for that page over the orders base URI */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $page->navigation());

        /** @And the base URI the relations render against */
        $base = '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id';

        /** @And the Link header template rendered per relation */
        $template = '<%s&page[number]=%d&page[size]=20>; rel="%s"';

        /** @When rendering the RFC 8288 Link header line */
        $header = $links->toHeader()->toArray()['Link'][0];

        /** @Then the line folds the five relations in navigation order */
        self::assertSame(implode(', ', [
            sprintf($template, $base, 3, 'self'),
            sprintf($template, $base, 1, 'first'),
            sprintf($template, $base, 2, 'prev'),
            sprintf($template, $base, 4, 'next'),
            sprintf($template, $base, 24, 'last')
        ]), $header);
    }

    public function testPipelineWhenCanonicalRequestGivenThenJsonBodyCarriesDataMetaAndLinks(): void
    {
        /** @Given the canonical request query parameters */
        $query = Query::from(parameters: [
            'filter' => 'status==paid;total=ge=100',
            'sort'   => '-created_at,id',
            'page'   => ['number' => '3', 'size' => '20']
        ]);

        /** @And the criteria parsed from those parameters */
        $criteria = Criteria::fromQuery(request: $query);

        /** @And the offset pagination pointing at the third page */
        $pagination = OffsetPagination::fromPage(page: 3, perPage: 20);

        /** @And the third page of a 480-element result */
        $page = OffsetPage::from(
            items: Collection::createFromEmpty(),
            total: 480,
            criteria: $criteria,
            pagination: $pagination
        );

        /** @And the navigation for that page over the orders base URI */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $page->navigation());

        /** @And the base URI the navigation links render against */
        $base = '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id';

        /** @When assembling the JSON:API body from the data, the meta, and the links */
        $body = [
            'data'  => $page->items()->toArray(keyPreservation: KeyPreservation::DISCARD),
            'meta'  => $page->metadata(),
            'links' => $links->toArray()
        ];

        /** @Then the body carries the empty data, the meta, and the five navigation links in order */
        self::assertSame([
            'data'  => [],
            'meta'  => [
                'total'        => 480,
                'has_next'     => true,
                'per_page'     => 20,
                'total_pages'  => 24,
                'current_page' => 3,
                'has_previous' => true
            ],
            'links' => [
                'self'  => sprintf('%s&page[number]=3&page[size]=20', $base),
                'first' => sprintf('%s&page[number]=1&page[size]=20', $base),
                'prev'  => sprintf('%s&page[number]=2&page[size]=20', $base),
                'next'  => sprintf('%s&page[number]=4&page[size]=20', $base),
                'last'  => sprintf('%s&page[number]=24&page[size]=20', $base)
            ]
        ], $body);
    }
}
