# Http Query

[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/tiny-blocks/http-query/blob/main/LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    + [Filtering with RSQL](#filtering-with-rsql)
    + [Sorting](#sorting)
    + [Offset pagination](#offset-pagination)
    + [Cursor pagination](#cursor-pagination)
    + [Rendering navigation links](#rendering-navigation-links)
    + [Configuring the schema](#configuring-the-schema)
* [FAQ](#faq)
* [License](#license)
* [Contributing](#contributing)

## Overview

A typed, framework- and database-agnostic toolkit for the query of an HTTP collection endpoint. It parses an incoming
request query string into typed specifications for filtering (RSQL), sorting, and pagination (offset and cursor), and it
renders the result as a JSON:API response carrying an RFC 8288 `Link` header and a body `links` object.

The library never touches a data store. It turns the query string into value objects the consumer applies to its own
store, and it renders the response the consumer returns. Every computation is O(1) value-object math over the inputs the
consumer supplies. The pagination style stays internal: the `Criteria` builds the result page, so the public API never
exposes a concrete pagination type.

It builds on the [tiny-blocks](https://github.com/tiny-blocks) ecosystem: it reads the query parameters of a PSR-7
request through [`tiny-blocks/http`](https://github.com/tiny-blocks/http), carries items in a
[`tiny-blocks/collection`](https://github.com/tiny-blocks/collection) `Collection`, encodes cursors through
[`tiny-blocks/encoder`](https://github.com/tiny-blocks/encoder), and renders the response and its `Link` header with the
`Response`, `Link`, and `LinkRelation` types from `tiny-blocks/http`.

## Installation

```bash
composer require tiny-blocks/http-query
```

## How to use

The entry point is `Criteria::fromQuery`, which reads the request query parameters from a PSR-7 request and produces a
`Criteria` carrying the filter, the sort, and the pagination. The `Criteria` then builds the result page and renders it,
so the pagination style stays internal.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Criteria;

# GET /v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=3&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(request: $request);
```

### Filtering with RSQL

The `filter` parameter is an RSQL expression. `Criteria::filtering()` returns the parsed expression tree as a `Filter`,
which is either a `Comparison` leaf or a `Group` composite. The consumer matches on the node type to translate the tree
to its own store.

```php
<?php

declare(strict_types=1);

use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Group;

function describe(Filter $filter): string
{
    return match (true) {
        $filter instanceof Comparison => sprintf(
            '%s %s %s',
            $filter->field(),
            $filter->operator()->value,
            implode(',', $filter->values())
        ),
        $filter instanceof Group => implode(
            $filter->operator()->value,
            array_map(describe(...), $filter->filters())
        )
    };
}

# filter=status==paid;total=ge=100  parses to a Group(AND) of two Comparison leaves.
```

The supported operators map to their RSQL tokens through the `Operator` enum (`==`, `!=`, `=lt=`, `=gt=`, `=le=`,
`=ge=`, `=in=`, `=out=`). The logical connectives map through the `LogicalOperator` enum, where `;` (AND) binds tighter
than `,` (OR), and parentheses group. A malformed expression raises `FilterExpressionIsInvalid`.

### Sorting

The `sort` parameter is a comma-separated list of fields, where a leading minus marks descending order, following the
JSON:API convention. `Criteria::sorting()` returns a `Sort` whose `Order` list preserves the request order.

```php
<?php

declare(strict_types=1);

use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Sort;

$sort = Sort::fromExpression(expression: '-created_at,id');

foreach ($sort->orders() as $order) {
    $order->field();                               # 'created_at' then 'id'
    $order->direction() === Direction::DESCENDING; # true then false
}
```

A malformed expression raises `SortExpressionIsInvalid`.

### Offset pagination

When no cursor is present, `Criteria::offsetPage` builds an `OffsetPage` from the items of the current page and the total
element count. The page derives the offset and the total pages from the request, so the consumer never builds the
pagination itself. The items are any `iterable`, and `items()` returns them as a `Collection`.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Criteria;

# GET /v1/orders?page[number]=3&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(request: $request);

/** @var iterable<mixed> $items */
$page = $criteria->offsetPage(items: $items, total: 480);

$page->hasNext();     # true
$page->totalPages();  # 24
$page->metadata();    # the JSON:API meta contents
```

Use `Criteria::offsetSlice` instead of `Criteria::offsetPage` when the total is unknown. The consumer fetches one element
beyond the page size, and the `OffsetSlice` trims it and reads its presence as the next-page hint.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Criteria;

# GET /v1/orders?page[number]=2&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(request: $request);

/** @var iterable<mixed> $items */
$slice = $criteria->offsetSlice(items: $items);

$slice->hasNext(); # inferred from the extra fetched element
```

### Cursor pagination

When the `cursor` parameter is present, `Criteria::cursorPage` builds a keyset `CursorPage`. A `Cursor` is an opaque,
URI-safe token wrapping the last-seen ordering key values, encoded through `tiny-blocks/encoder`. The `keysOf` closure
extracts the ordering key values from an element, and the page derives the forward and backward cursors from them.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Criteria;

# GET /v1/orders?sort=-created_at,id&page[cursor]=BS3RvKY4LqEjYD19mQ0mCpJ&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(request: $request);

/** @var iterable<array{id: int, created_at: string}> $items */
$cursorPage = $criteria->cursorPage(
    items: $items,
    keysOf: static fn(array $order): array => [$order['created_at'], $order['id']]
);

$cursorPage->hasNext();  # Inferred from the extra fetched element
$cursorPage->next();     # The CursorPagination for the next page, or null
$cursorPage->previous(); # The CursorPagination for the previous page, or null
```

An invalid cursor token raises `CursorIsInvalid` when it is decoded.

### Rendering navigation links

`toResponse` renders a result as a JSON:API response in one call. It builds the body from the data, the meta, and the
navigation links, and folds the same relations into an RFC 8288 `Link` header. It returns a PSR-7 `ResponseInterface`.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Criteria;

# GET /v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=3&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(request: $request);

/** @var iterable<mixed> $items */
$response = $criteria->offsetPage(items: $items, total: 480)->toResponse(baseUri: '/v1/orders');
```

For full control over the body, assemble it from the parts. `Links::from` reads the navigation a result exposes through
`result->navigation()`, swaps the criteria's pagination for each target, and serializes it back through `Criteria::toUri`,
so the filter and the sort are preserved in every URI. `toArray()` returns the JSON:API body `links` object and
`toHeader()` returns an RFC 8288 `Link`.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\Http\Server\Response;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\Links;

# GET /v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=3&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(request: $request);

/** @var iterable<mixed> $items */
$page = $criteria->offsetPage(items: $items, total: 480);
$links = Links::from(baseUri: '/v1/orders', criteria: $criteria, navigation: $page->navigation());

$response = Response::ok([
    'data'  => $page->items()->toArray(),
    'meta'  => $page->metadata(),
    'links' => $links->toArray()
], $links->toHeader());
```

The body carries the navigation with the filter and the sort preserved in every URI.

```json
{
  "data": [],
  "meta": {
    "total": 480,
    "has_next": true,
    "per_page": 20,
    "total_pages": 24,
    "current_page": 3,
    "has_previous": true
  },
  "links": {
    "self":  "/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=3&page[size]=20",
    "first": "/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=1&page[size]=20",
    "prev":  "/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=2&page[size]=20",
    "next":  "/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=4&page[size]=20",
    "last":  "/v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=24&page[size]=20"
  }
}
```

The header folds the same relations into one RFC 8288 `Link` line.

```text
Link: </v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=3&page[size]=20>; rel="self",
      </v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=1&page[size]=20>; rel="first",
      </v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=2&page[size]=20>; rel="prev",
      </v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=4&page[size]=20>; rel="next",
      </v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=24&page[size]=20>; rel="last"
```

The `links` keys are the canonical JSON:API relations, in the semantic order below. Unavailable relations are omitted,
so the first page carries no `prev` and the last page carries no `next`. An `OffsetSlice` exposes `self`, `first`,
`prev`, and `next` (no `last`), and a `CursorPage` exposes only `self`, `prev`, and `next`.

| Relation | Meaning            |
|----------|--------------------|
| `self`   | The current page.  |
| `first`  | The first page.    |
| `prev`   | The previous page. |
| `next`   | The next page.     |
| `last`   | The last page.     |

### Configuring the schema

The query parameter names follow JSON:API and are fixed: `filter`, `sort`, and the `page` family (`page[number]`,
`page[size]`, `page[cursor]`). `Schema` carries only the page-size bounds. `Schema::default()` is applied when
`Criteria::fromQuery` receives no schema, and the fluent `with*` copies override either bound.

| Setting          | Default | Meaning                                         |
|------------------|---------|-------------------------------------------------|
| `defaultPerPage` | `20`    | The page size applied when the query omits one. |
| `maxPerPage`     | `100`   | The maximum allowed page size.                  |

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Criteria;
use TinyBlocks\HttpQuery\Schema;

$schema = Schema::default()
    ->withMaxPerPage(maxPerPage: 200)
    ->withDefaultPerPage(defaultPerPage: 50);

# GET /v1/orders?filter=status==paid&page[size]=50
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(request: $request, schema: $schema);
```

A page size above `maxPerPage` raises `PageSizeOutOfRange`.

## FAQ

### 01. Why does the library never touch a data store?

The query of a collection has two halves: deciding what to fetch, and fetching it. This library owns only the first
half. It parses the request into typed specifications and renders the response navigation, leaving the consumer free to
apply the specifications to any store, SQL, a document database, or an in-memory list. Keeping the store out makes every
operation pure value-object math and keeps the library framework- and database-agnostic.

### 02. Why RSQL for filtering instead of ad-hoc query parameters?

RSQL is a small, URI-safe grammar with a published reference, so the filter survives a query string without encoding and
the same expression reads the same on the client and the server. The parser produces an immutable expression tree the
consumer walks, rather than a flat map of parameters that cannot express grouping or disjunction.

> Zdenek Jirutka, *RSQL / FIQL parser* (https://github.com/jirutka/rsql-parser).

### 03. Why are both offset and cursor pagination supported?

Offset pagination answers "how many pages are there" and "jump to page N", which an `OffsetPage` with a total provides.
Cursor pagination answers "give me the next slice after this point" without a total, which scales to large collections
and avoids the drift of offset pagination under concurrent writes. The library models both, plus an `OffsetSlice` for
offset navigation without a total.

### 04. How are the `meta` keys ordered?

The keys are snake_case and ordered by key length ascending, with an alphabetical tiebreak. The order is fixed so the
serialized response is deterministic across requests.

## License

Http Query is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
