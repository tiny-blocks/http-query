<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Cursor\Criteria as CursorCriteria;
use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Offset\Criteria as OffsetCriteria;
use TinyBlocks\HttpQuery\Operator;
use TinyBlocks\HttpQuery\Schema;

final class EndToEndTest extends TestCase
{
    public function testPipelineWhenOffsetRequestGivenThenPageRendersTheResponse(): void
    {
        /** @Given the query contract of the orders endpoint */
        $schema = Schema::create()
            ->sortable(fields: ['created_at', 'id'])
            ->filterable(field: 'total', operators: [Operator::GREATER_THAN_OR_EQUAL])
            ->filterable(field: 'status', operators: [Operator::EQUAL]);

        /** @And the canonical request query parameters */
        $query = Query::from(parameters: [
            'filter' => 'status==paid;total=ge=100',
            'sort'   => '-created_at,id',
            'page'   => ['number' => '3', 'size' => '20']
        ]);

        /** @And the criteria parsed from those parameters */
        $criteria = OffsetCriteria::fromQuery(schema: $schema, request: $query);

        /** @And the base URI the navigation links render against */
        $base = '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id';

        /** @And the third page of a 480-element result built from the criteria */
        $page = $criteria->page(items: ['a', 'b'], total: 480);

        /** @When rendering the page as a JSON:API response over the orders base URI */
        $response = $page->toResponse(baseUri: '/v1/orders');

        /** @Then the body carries the data, the meta, and the navigation links preserving filter and sort */
        self::assertSame([
            'data'  => ['a', 'b'],
            'meta'  => [
                'total'        => 480,
                'per_page'     => 20,
                'total_pages'  => 24,
                'current_page' => 3,
                'has_next'     => true,
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

    public function testPipelineWhenCursorRequestGivenThenCursorPageRendersTheResponse(): void
    {
        /** @Given the query contract of the orders endpoint */
        $schema = Schema::create()->sortable(fields: ['id']);

        /** @And an opaque token produced from ordering key values */
        $token = Token::fromKeys(keys: [5])->toString();

        /** @And query parameters carrying a sort, that cursor, and a page size of two */
        $query = Query::from(parameters: ['sort' => 'id', 'page' => ['cursor' => $token, 'size' => '2']]);

        /** @And a cursor page built through the keyset view over the array rows fetched */
        $page = CursorCriteria::fromQuery(schema: $schema, request: $query)
            ->keyset()
            ->page(items: [['id' => 10], ['id' => 20], ['id' => 30]]);

        /** @And the base URI the keyset links render against */
        $base = '/v1/orders?sort=id';

        /** @When rendering the cursor page as a JSON:API response over the orders base URI */
        $response = $page->toResponse(baseUri: '/v1/orders');

        /** @Then the body carries the data, the meta, and the forward-only keyset links preserving the sort */
        self::assertSame([
            'data'  => [['id' => 10], ['id' => 20]],
            'meta'  => [
                'per_page' => 2,
                'has_next' => true
            ],
            'links' => [
                'self' => sprintf('%s&page[cursor]=%s&page[size]=2', $base, $token),
                'next' => sprintf('%s&page[cursor]=%s&page[size]=2', $base, Token::fromKeys(keys: [20])->toString())
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testPipelineWhenOffsetRequestGivenThenLinkHeaderFoldsEveryRelation(): void
    {
        /** @Given the query contract of the orders endpoint */
        $schema = Schema::create()
            ->sortable(fields: ['created_at', 'id'])
            ->filterable(field: 'total', operators: [Operator::GREATER_THAN_OR_EQUAL])
            ->filterable(field: 'status', operators: [Operator::EQUAL]);

        /** @And the canonical request query parameters */
        $query = Query::from(parameters: [
            'filter' => 'status==paid;total=ge=100',
            'sort'   => '-created_at,id',
            'page'   => ['number' => '3', 'size' => '20']
        ]);

        /** @And the criteria parsed from those parameters */
        $criteria = OffsetCriteria::fromQuery(schema: $schema, request: $query);

        /** @And the base URI the relations render against */
        $base = '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id';

        /** @And the Link header template rendered per relation */
        $template = '<%s&page[number]=%d&page[size]=20>; rel="%s"';

        /** @When rendering the page as a JSON:API response and reading its RFC 8288 Link header line */
        $header = $criteria->page(items: [], total: 480)->toResponse(baseUri: '/v1/orders')->getHeaderLine('Link');

        /** @Then the line folds the five relations in navigation order */
        self::assertSame(implode(', ', [
            sprintf($template, $base, 3, 'self'),
            sprintf($template, $base, 1, 'first'),
            sprintf($template, $base, 2, 'prev'),
            sprintf($template, $base, 4, 'next'),
            sprintf($template, $base, 24, 'last')
        ]), $header);
    }

    public function testPipelineWhenRequestCarriesUndeclaredParametersThenLinksLeaveThemOut(): void
    {
        /** @Given a request carrying a window the default schema never declared */
        $query = Query::from(parameters: ['from' => '2026-10-01', 'to' => '2026-10-31', 'page' => ['size' => '1']]);

        /** @When rendering the first page of a two-element result as a JSON:API response */
        $response = OffsetCriteria::fromQueryWithDefaultSchema(request: $query)
            ->page(items: ['a'], total: 2)
            ->toResponse(baseUri: '/v1/overrides');

        /** @Then every link renders only the pagination, exactly as before */
        self::assertSame([
            'data'  => ['a'],
            'meta'  => [
                'total'        => 2,
                'per_page'     => 1,
                'total_pages'  => 2,
                'current_page' => 1,
                'has_next'     => true,
                'has_previous' => false
            ],
            'links' => [
                'self'  => '/v1/overrides?page[number]=1&page[size]=1',
                'first' => '/v1/overrides?page[number]=1&page[size]=1',
                'next'  => '/v1/overrides?page[number]=2&page[size]=1',
                'last'  => '/v1/overrides?page[number]=2&page[size]=1'
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testPipelineWhenDeclaredParametersAreAbsentFromTheRequestThenLinksLeaveThemOut(): void
    {
        /** @Given the query contract of an endpoint windowed by from and to */
        $schema = Schema::create()->preserving(parameters: ['from', 'to']);

        /** @And a request carrying only the window start */
        $query = Query::from(parameters: ['from' => '2026-10-01', 'page' => ['size' => '1']]);

        /** @When rendering the first page of a two-element result as a JSON:API response */
        $response = OffsetCriteria::fromQuery(schema: $schema, request: $query)
            ->page(items: ['a'], total: 2)
            ->toResponse(baseUri: '/v1/overrides');

        /** @Then every link carries the window start and no empty window end */
        self::assertSame([
            'data'  => ['a'],
            'meta'  => [
                'total'        => 2,
                'per_page'     => 1,
                'total_pages'  => 2,
                'current_page' => 1,
                'has_next'     => true,
                'has_previous' => false
            ],
            'links' => [
                'self'  => '/v1/overrides?from=2026-10-01&page[number]=1&page[size]=1',
                'first' => '/v1/overrides?from=2026-10-01&page[number]=1&page[size]=1',
                'next'  => '/v1/overrides?from=2026-10-01&page[number]=2&page[size]=1',
                'last'  => '/v1/overrides?from=2026-10-01&page[number]=2&page[size]=1'
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testPipelineWhenDeclaredParameterCarriesBracketedKeysThenEveryLinkPreservesThem(): void
    {
        /** @Given the query contract of an endpoint windowed by a bracketed window parameter */
        $schema = Schema::create()->preserving(parameters: ['window']);

        /** @And a request carrying window[from] and window[to] */
        $query = Query::from(parameters: [
            'page'   => ['size' => '1'],
            'window' => ['from' => '2026-10-01', 'to' => '2026-10-31']
        ]);

        /** @When rendering the first page of a two-element result as a JSON:API response */
        $response = OffsetCriteria::fromQuery(schema: $schema, request: $query)
            ->page(items: ['a'], total: 2)
            ->toResponse(baseUri: '/v1/overrides');

        /** @Then every link preserves both bracketed keys */
        self::assertSame([
            'data'  => ['a'],
            'meta'  => [
                'total'        => 2,
                'per_page'     => 1,
                'total_pages'  => 2,
                'current_page' => 1,
                'has_next'     => true,
                'has_previous' => false
            ],
            'links' => [
                'self'  => '/v1/overrides?window[from]=2026-10-01&window[to]=2026-10-31&page[number]=1&page[size]=1',
                'first' => '/v1/overrides?window[from]=2026-10-01&window[to]=2026-10-31&page[number]=1&page[size]=1',
                'next'  => '/v1/overrides?window[from]=2026-10-01&window[to]=2026-10-31&page[number]=2&page[size]=1',
                'last'  => '/v1/overrides?window[from]=2026-10-01&window[to]=2026-10-31&page[number]=2&page[size]=1'
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testPipelineWhenSliceRequestCarriesDeclaredParametersThenEveryLinkPreservesThem(): void
    {
        /** @Given the query contract of an endpoint windowed by from and to */
        $schema = Schema::create()->preserving(parameters: ['from', 'to']);

        /** @And a request carrying the window and the first page */
        $query = Query::from(parameters: ['from' => '2026-10-01', 'to' => '2026-10-31', 'page' => ['size' => '1']]);

        /** @When rendering a slice fetched with one element beyond the page size as a JSON:API response */
        $response = OffsetCriteria::fromQuery(schema: $schema, request: $query)
            ->slice(items: ['a', 'b'])
            ->toResponse(baseUri: '/v1/overrides');

        /** @Then every link preserves the window */
        self::assertSame([
            'data'  => ['a'],
            'meta'  => [
                'per_page'     => 1,
                'current_page' => 1,
                'has_next'     => true,
                'has_previous' => false
            ],
            'links' => [
                'self'  => '/v1/overrides?from=2026-10-01&to=2026-10-31&page[number]=1&page[size]=1',
                'first' => '/v1/overrides?from=2026-10-01&to=2026-10-31&page[number]=1&page[size]=1',
                'next'  => '/v1/overrides?from=2026-10-01&to=2026-10-31&page[number]=2&page[size]=1'
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testPipelineWhenCursorRequestCarriesDeclaredParametersThenEveryLinkPreservesThem(): void
    {
        /** @Given the query contract of an endpoint scoped by a released slot */
        $schema = Schema::create()
            ->sortable(fields: ['id'])
            ->preserving(parameters: ['slot_start_time', 'slot_end_time']);

        /** @And a request carrying the released slot, a sort, and a page size of one */
        $query = Query::from(parameters: [
            'sort'            => 'id',
            'page'            => ['size' => '1'],
            'slot_end_time'   => '2026-10-14T11:00:00Z',
            'slot_start_time' => '2026-10-14T10:00:00Z'
        ]);

        /** @And the base URI carrying the sort and the released slot */
        $base = '/v1/eligible?sort=id&slot_end_time=2026-10-14T11%3A00%3A00Z&slot_start_time=2026-10-14T10%3A00%3A00Z';

        /** @When rendering the cursor page built through the keyset view as a JSON:API response */
        $response = CursorCriteria::fromQuery(schema: $schema, request: $query)
            ->keyset()
            ->page(items: [['id' => 10], ['id' => 20]])
            ->toResponse(baseUri: '/v1/eligible');

        /** @Then the self and next links preserve the released slot */
        self::assertSame([
            'data'  => [['id' => 10]],
            'meta'  => [
                'per_page' => 1,
                'has_next' => true
            ],
            'links' => [
                'self' => sprintf('%s&page[size]=1', $base),
                'next' => sprintf('%s&page[cursor]=%s&page[size]=1', $base, Token::fromKeys(keys: [10])->toString())
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testPipelineWhenOffsetRequestCarriesDeclaredParametersThenEveryLinkPreservesThem(): void
    {
        /** @Given the query contract of an endpoint windowed by from and to */
        $schema = Schema::create()->sortable(fields: ['id'])->preserving(parameters: ['from', 'to']);

        /** @And a request carrying the window, an undeclared parameter, a sort, and the second page */
        $query = Query::from(parameters: [
            'to'    => '2026-10-31T23:59:59+00:00',
            'from'  => '2026-10-01T00:00:00+00:00',
            'sort'  => 'id',
            'page'  => ['number' => '2', 'size' => '1'],
            'debug' => 'true'
        ]);

        /** @And the base URI carrying the sort and the window as the request ordered it */
        $base = '/overrides?sort=id&to=2026-10-31T23%3A59%3A59%2B00%3A00&from=2026-10-01T00%3A00%3A00%2B00%3A00';

        /** @When rendering the second page of a three-element result as a JSON:API response */
        $response = OffsetCriteria::fromQuery(schema: $schema, request: $query)
            ->page(items: ['b'], total: 3)
            ->toResponse(baseUri: '/overrides');

        /** @Then every link preserves the window and drops the undeclared parameter */
        self::assertSame([
            'data'  => ['b'],
            'meta'  => [
                'total'        => 3,
                'per_page'     => 1,
                'total_pages'  => 3,
                'current_page' => 2,
                'has_next'     => true,
                'has_previous' => true
            ],
            'links' => [
                'self'  => sprintf('%s&page[number]=2&page[size]=1', $base),
                'first' => sprintf('%s&page[number]=1&page[size]=1', $base),
                'prev'  => sprintf('%s&page[number]=1&page[size]=1', $base),
                'next'  => sprintf('%s&page[number]=3&page[size]=1', $base),
                'last'  => sprintf('%s&page[number]=3&page[size]=1', $base)
            ]
        ], json_decode($response->getBody()->getContents(), true));
    }

    public function testPipelineWhenCursorRequestCarriesDeclaredParametersThenLinkHeaderPreservesThem(): void
    {
        /** @Given the query contract of an endpoint scoped by a released slot */
        $schema = Schema::create()
            ->sortable(fields: ['id'])
            ->preserving(parameters: ['slot_start_time', 'slot_end_time']);

        /** @And a request carrying the released slot, a sort, and a page size of one */
        $query = Query::from(parameters: [
            'sort'            => 'id',
            'page'            => ['size' => '1'],
            'slot_start_time' => '2026-10-14T10:00:00Z',
            'slot_end_time'   => '2026-10-14T11:00:00Z'
        ]);

        /** @And the base URI carrying the sort and the released slot */
        $base = '/v1/eligible?sort=id&slot_start_time=2026-10-14T10%3A00%3A00Z&slot_end_time=2026-10-14T11%3A00%3A00Z';

        /** @When rendering the cursor page and reading its RFC 8288 Link header line */
        $header = CursorCriteria::fromQuery(schema: $schema, request: $query)
            ->keyset()
            ->page(items: [['id' => 10], ['id' => 20]])
            ->toResponse(baseUri: '/v1/eligible')
            ->getHeaderLine('Link');

        /** @Then the self and next relations preserve the released slot */
        self::assertSame(implode(', ', [
            sprintf('<%s&page[size]=1>; rel="self"', $base),
            sprintf('<%s&page[cursor]=%s&page[size]=1>; rel="next"', $base, Token::fromKeys(keys: [10])->toString())
        ]), $header);
    }

    public function testPipelineWhenOffsetRequestCarriesDeclaredParametersThenLinkHeaderPreservesThem(): void
    {
        /** @Given the query contract of an endpoint windowed by from and to */
        $schema = Schema::create()->preserving(parameters: ['from', 'to']);

        /** @And a request carrying the window and the first page */
        $query = Query::from(parameters: ['from' => '2026-10-01', 'to' => '2026-10-31', 'page' => ['size' => '1']]);

        /** @And the Link header template rendered per relation */
        $template = '</v1/overrides?from=2026-10-01&to=2026-10-31&page[number]=%d&page[size]=1>; rel="%s"';

        /** @When rendering the first page of a two-element result and reading its RFC 8288 Link header line */
        $header = OffsetCriteria::fromQuery(schema: $schema, request: $query)
            ->page(items: ['a'], total: 2)
            ->toResponse(baseUri: '/v1/overrides')
            ->getHeaderLine('Link');

        /** @Then every relation in the line preserves the window */
        self::assertSame(implode(', ', [
            sprintf($template, 1, 'self'),
            sprintf($template, 1, 'first'),
            sprintf($template, 2, 'next'),
            sprintf($template, 2, 'last')
        ]), $header);
    }
}
