<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\Collection\Collection;
use TinyBlocks\Collection\KeyPreservation;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\Links;
use TinyBlocks\HttpQuery\Page;
use TinyBlocks\HttpQuery\Pagination;

final class EndToEndTest extends TestCase
{
    public function testPipelineWhenCanonicalRequestGivenThenLinkHeaderFoldsEveryRelation(): void
    {
        /** @Given the canonical request query parameters */
        $query = Query::from(parameters: [
            'filter'   => 'status==paid;total=ge=100',
            'sort'     => '-created_at,id',
            'page'     => '3',
            'per_page' => '20'
        ]);

        /** @And the criteria parsed from those parameters */
        $criteria = Criteria::fromQuery(query: $query);

        /** @And the offset pagination pointing at the third page */
        $pagination = Pagination::fromPage(page: 3, perPage: 20);

        /** @And the third page of a 480-element result */
        $page = Page::from(items: Collection::createFromEmpty(), total: 480, pagination: $pagination);

        /** @And the navigation for that page over the orders base URI */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $page->navigation());

        /** @When rendering the RFC 8288 Link header line */
        $header = $links->toHeader()->toArray()['Link'][0];

        /** @Then the line folds the five relations in navigation order */
        self::assertSame(implode(', ', [
            '</v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=3&per_page=20>; rel="self"',
            '</v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=1&per_page=20>; rel="first"',
            '</v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=2&per_page=20>; rel="prev"',
            '</v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=4&per_page=20>; rel="next"',
            '</v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=24&per_page=20>; rel="last"'
        ]), $header);
    }

    public function testPipelineWhenCanonicalRequestGivenThenJsonBodyCarriesDataMetaAndLinks(): void
    {
        /** @Given the canonical request query parameters */
        $query = Query::from(parameters: [
            'filter'   => 'status==paid;total=ge=100',
            'sort'     => '-created_at,id',
            'page'     => '3',
            'per_page' => '20'
        ]);

        /** @And the criteria parsed from those parameters */
        $criteria = Criteria::fromQuery(query: $query);

        /** @And the offset pagination pointing at the third page */
        $pagination = Pagination::fromPage(page: 3, perPage: 20);

        /** @And the third page of a 480-element result */
        $page = Page::from(items: Collection::createFromEmpty(), total: 480, pagination: $pagination);

        /** @And the navigation for that page over the orders base URI */
        $links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $page->navigation());

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
                'self'  => '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=3&per_page=20',
                'first' => '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=1&per_page=20',
                'prev'  => '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=2&per_page=20',
                'next'  => '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=4&per_page=20',
                'last'  => '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page=24&per_page=20'
            ]
        ], $body);
    }
}
