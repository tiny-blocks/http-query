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
        $criteria = OffsetCriteria::fromQuery(request: $query, schema: $schema);

        /** @And the base URI the navigation links render against */
        $base = '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id';

        /** @And the third page of a 480-element result built from the criteria */
        $page = $criteria->page(total: 480, items: ['a', 'b']);

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
        $criteria = OffsetCriteria::fromQuery(request: $query, schema: $schema);

        /** @And the base URI the relations render against */
        $base = '/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id';

        /** @And the Link header template rendered per relation */
        $template = '<%s&page[number]=%d&page[size]=20>; rel="%s"';

        /** @When rendering the page as a JSON:API response and reading its RFC 8288 Link header line */
        $header = $criteria->page(total: 480, items: [])->toResponse(baseUri: '/v1/orders')->getHeaderLine('Link');

        /** @Then the line folds the five relations in navigation order */
        self::assertSame(implode(', ', [
            sprintf($template, $base, 3, 'self'),
            sprintf($template, $base, 1, 'first'),
            sprintf($template, $base, 2, 'prev'),
            sprintf($template, $base, 4, 'next'),
            sprintf($template, $base, 24, 'last')
        ]), $header);
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
        $page = CursorCriteria::fromQuery(request: $query, schema: $schema)
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
}
