# Http Query

[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/tiny-blocks/http-query/blob/main/LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    + [Declaring the query contract](#declaring-the-query-contract)
    + [Filtering with RSQL](#filtering-with-rsql)
    + [Sorting](#sorting)
    + [Offset pagination](#offset-pagination)
    + [Cursor pagination](#cursor-pagination)
    + [Building the store query](#building-the-store-query)
    + [Rendering navigation links](#rendering-navigation-links)
* [FAQ](#faq)
* [License](#license)
* [Contributing](#contributing)

## Overview

A typed, framework-independent toolkit for querying an HTTP collection endpoint. The endpoint declares the query
contract once on a `Schema`, the library parses an incoming request query string against it, and the consumer reads an
already-validated result: a conjunction of comparisons for filtering, an effective sort, and a pagination view. The
result renders as a JSON:API response carrying an RFC 8288 `Link` header and a body `links` object.

The library never touches a data store. It turns the query string into value objects the consumer applies to its own
store, and it renders the response the consumer returns. Every computation is O (1) value-object math over the inputs
the consumer supplies.

The pagination approach is a fixed decision of the endpoint, not a runtime property of a request. The public API splits
into two contexts, `Cursor` and `Offset`, each complete and approach-only, with the building blocks common to both at
the root. Each page renders a `self` link consistent with its own approach, so a cursor page renders a cursor `self`
and an offset page renders an offset `self`.

## Installation

```bash
composer require tiny-blocks/http-query
```

## How to use

There are two entry points, one per pagination approach. `TinyBlocks\HttpQuery\Offset\Criteria` reads an offset request
(`page[number]`, `page[size]`) and builds offset pages, while `TinyBlocks\HttpQuery\Cursor\Criteria` reads a keyset
request (`page[cursor]`, `page[size]`) and builds forward-only cursor pages. Both read the same `filter` and `sort`
parameters, validate them against the schema, and expose the same `comparisons()` and `sort()` building blocks.

### Declaring the query contract

`Schema` is the contract of the query an endpoint accepts. `filterable` declares a field with its permitted operators
and, optionally, the permitted values and the `ValueKind` every value must match. `sortable` declares the fields the
client may sort by. `defaultSort` declares the sort applied when the client sends none. `maxPerPage` and
`defaultPerPage` bound the page size. The query parameter names follow JSON:API and are fixed: `filter`, `sort`, and the
`page` family.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Operator;
use TinyBlocks\HttpQuery\Schema;
use TinyBlocks\HttpQuery\Sort;
use TinyBlocks\HttpQuery\ValueKind;

$schema = Schema::create()
    ->maxPerPage(maxPerPage: 100)
    ->sortable(fields: ['created_at', 'id'])
    ->defaultSort(sort: Sort::fromExpression(expression: '-created_at'))
    ->filterable(field: 'total', operators: [Operator::GREATER_THAN_OR_EQUAL], valueKind: ValueKind::INTEGER)
    ->filterable(field: 'status', operators: [Operator::EQUAL, Operator::IN], allowedValues: ['paid', 'pending']);

# GET /v1/orders?filter=status==paid;total=ge=100&sort=-created_at,id&page[number]=3&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(schema: $schema, request: $request);
```

For an endpoint that declares no contract, `Criteria::fromQueryWithDefaultSchema` is the request-only entry point. It
applies the default schema, an empty contract: the default page-size bounds, no filterable or sortable field, and no
default sort. Any incoming filter or sort is then rejected. Both `Offset\Criteria` and `Cursor\Criteria` expose it, so
either pagination parses a request without building a schema.

| Setting          | Default | Meaning                                         |
|------------------|---------|-------------------------------------------------|
| `defaultPerPage` | `20`    | The page size applied when the query omits one. |
| `maxPerPage`     | `100`   | The maximum allowed page size.                  |

A page size above `maxPerPage` raises `PageSizeOutOfRange`.

### Filtering with RSQL

The `filter` parameter is an RSQL expression. It is validated against the schema at parse and flattened into the
conjunction of comparisons the consumer applies to its store. `Criteria::comparisons()` returns that validated
`list<Comparison>`, empty when there is no filter.

```php
<?php

declare(strict_types=1);

use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Operator;

# filter=status==paid;total=ge=100  ->  a validated list<Comparison>.
/** @var Criteria $criteria */
foreach ($criteria->comparisons() as $comparison) {
    $comparison->field();                                 # 'status', then 'total'.
    $comparison->values();                                # ['paid'], then ['100'].
    $comparison->firstValue();                            # The first compared value, 'paid'.
    $comparison->hasField(field: 'status');               # True for the status leaf.
    $comparison->hasOperator(operator: Operator::EQUAL);  # True for an equality leaf.
}
```

The supported operators map to their RSQL tokens through the `Operator` enum (`==`, `!=`, `=lt=`, `=gt=`, `=le=`,
`=ge=`, `=in=`, `=out=`, `=sw=`). The logical connectives map through the `LogicalOperator` enum, where `;` (AND)
binds tighter than `,` (OR), and parentheses group. By default, the filter must be a single comparison or an AND group
of comparisons, and any other shape raises `FilterShapeNotSupported`. A schema that calls `allowDisjunction`
accepts OR groups and nested groups, validates every leaf the same way, and the consumer then reads the full tree from
`Criteria::filter()` to render it. A malformed expression raises `FilterExpressionIsInvalid`, and anything outside the
contract raises a dedicated exception, each implementing `HttpQueryException`.

| Exception                   | Raised when                                                                       |
|-----------------------------|-----------------------------------------------------------------------------------|
| `FilterExpressionIsInvalid` | The filter expression cannot be parsed as RSQL.                                   |
| `FilterShapeNotSupported`   | The filter is an OR group or a nested group and the schema disallows disjunction. |
| `FilterFieldNotAllowed`     | A comparison targets a field that was never declared filterable.                  |
| `FilterOperatorNotAllowed`  | A comparison uses an operator not allowed for its field.                          |
| `FilterValueNotAllowed`     | A compared value falls outside the permitted set or the expected kind.            |

### Prefix matching with `=sw=`

`=sw=` compares the value as a **literal prefix** of the column. It renders as an anchored `LIKE` so a B-tree index on
the column is still usable:

```sql
cli.name LIKE :filter_0 ESCAPE '!'
```

Only the trailing `%` is added by the library. Every `%`, `_`, and `!` inside the value is escaped before binding, so a
search for `100%` matches a name starting with the literal `100%` instead of expanding into a wildcard. There is no
unanchored counterpart (contains, ends with) by design: an unanchored comparison cannot use the index and degrades the
listing into a table scan as the data grows.

The escape character is `!` rather than the customary backslash on purpose. A backslash has to be written `'\\'` in a
SQL literal under the default MySQL mode and `'\'` under `NO_BACKSLASH_ESCAPES`, so no single spelling is valid in both.
Under `NO_BACKSLASH_ESCAPES` the backslash form fails with `Incorrect arguments to ESCAPE`. A `!` needs no quoting in
either mode, so the rendered predicate is independent of the server configuration.

**Case and accent sensitivity come from the column collation, not from this library.** The rendered SQL carries no
`COLLATE`, so a column declared `utf8mb4_0900_ai_ci` (accent-insensitive, case-insensitive) makes `jose` match `José`,
while a binary collation matches exactly. Declare the collation the endpoint promises.

`ValueKind` is the kind a value is validated against. For the multivalued operators (`=in=`, `=out=`) every value is
checked.

| Kind                  | Matches                                                |
|-----------------------|--------------------------------------------------------|
| `ValueKind::STRING`   | A non-empty string.                                    |
| `ValueKind::INTEGER`  | An optionally signed sequence of digits, `-7` or `42`. |
| `ValueKind::DATETIME` | An ISO-8601 date or date-time, `2023-01-15T10:30:00Z`. |

### Sorting

The `sort` parameter is a comma-separated list of fields, where a leading minus marks descending order, following the
JSON:API convention. `Criteria::sort()` returns the effective `Sort`: the client sort when present, validated so every
field is declared `sortable`, otherwise the schema `defaultSort`. An order by an undeclared field raises
`SortFieldNotAllowed`, and a malformed expression raises `SortExpressionIsInvalid`.

```php
<?php

declare(strict_types=1);

use TinyBlocks\HttpQuery\Direction;
use TinyBlocks\HttpQuery\Offset\Criteria;

# sort=-created_at,id  ->  the effective Sort, ordered as requested.
/** @var Criteria $criteria */
foreach ($criteria->sort()->orders() as $order) {
    $order->field();                               # 'created_at' then 'id'.
    $order->direction() === Direction::DESCENDING; # true then false.
}
```

### Offset pagination

`Offset\Criteria::page` builds an `Offset\Page` from the total element count and the items of the current page. The page
derives the offset and the total pages from the request, so the consumer never builds the pagination itself. The items
are any `iterable`, and `items()` returns them as a `Collection`.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Schema;

# GET /v1/orders?page[number]=3&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(schema: Schema::create(), request: $request);

/** @var iterable<mixed> $items */
$page = $criteria->page(total: 480, items: $items);

$page->hasNext();     # true
$page->metadata();    # The JSON:API meta contents.
$page->totalPages();  # 24
```

Use `Offset\Criteria::slice` instead of `page` when the total is unknown. The consumer fetches one element beyond the
page size, and the `Offset\Slice` trims it and reads its presence as the next-page hint.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Schema;

# GET /v1/orders?page[number]=2&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(schema: Schema::create(), request: $request);

/** @var iterable<mixed> $items */
$slice = $criteria->slice(items: $items);

$slice->hasNext(); # Inferred from the extra fetched element.
```

### Cursor pagination

Cursor pages are forward-only. `Cursor\Criteria::keyset` pairs the effective sort with the cursor view, exposing the
seek inputs the consumer needs before fetching: the page size, the orders, and the incoming cursor key values keyed by
sort field. A keyset needs a deterministic order, so the schema must declare a `defaultSort` or the client must sort,
otherwise `keyset` raises `SortIsRequired`. A `Token` is an opaque, URI-safe value wrapping the last-seen ordering key
values, encoded as URL-safe base64.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Cursor\Criteria;
use TinyBlocks\HttpQuery\Schema;

$schema = Schema::create()->sortable(fields: ['created_at', 'id']);

# GET /v1/orders?sort=-created_at,id&page[cursor]=BS3RvKY4LqEjYD19mQ0mCpJ&page[size]=20
/** @var ServerRequestInterface $request */
$keyset = Criteria::fromQuery(schema: $schema, request: $request)->keyset();

$keyset->limit()->toInteger();  # The page size, 20.
$keyset->orders();              # The list<Order> the seek is ordered by.
$keyset->cursor();              # ['created_at' => ..., 'id' => ...], null per field on the first page.
```

The seek inputs feed the store query. The consumer fetches one element beyond the page size and hands the rows to
`page`, which trims the extra element and reads its presence as the next-page hint. The ordering keys default to the
sort fields read from each array-shaped row, so the cursor keys come from the source rows. Pass an explicit `keysOf` to
extract them differently.

```php
<?php

declare(strict_types=1);

use TinyBlocks\HttpQuery\Cursor\Keyset;

/** @var Keyset $keyset */
/** @var iterable<array{id: int, created_at: string}> $items */
$cursorPage = $keyset->page(items: $items);

$cursorPage->next();    # The Cursor\Pagination for the next page, or null.
$cursorPage->hasNext(); # Inferred from the extra fetched element.
```

`map` projects the items for rendering while preserving the cursor, so raw rows drive the ordering keys and a view is
rendered from the same page.

```php
$cursorPage->map(transformation: static fn(array $row): array => ['id' => $row['id']]);
```

An invalid cursor token raises `CursorIsInvalid` when it is decoded.

### Building the store query

The library decides what to fetch and hands the consumer typed SQL fragments to apply against its own store. It builds
no statement: the `SELECT`, the `FROM`, the joins, the `LIMIT`, and the dialect stay with the consumer (or its query
builder). Every fragment is a `Clause\SqlClause` exposing `sql()`, `parameters()`, and `isEmpty()`, so the consumer
programs the `WHERE` assembly against one abstraction.

`Clause\FilterColumns` maps each queryable field to its column. It is an immutable fluent builder, with `plain`,
`boolean`, and `wrapped` shortcuts, and `with` for a custom `FilterColumn`. The same mapping feeds the filter, the seek,
and the sort.

```php
<?php

declare(strict_types=1);

use TinyBlocks\HttpQuery\Clause\Every;
use TinyBlocks\HttpQuery\Clause\FilterColumns;
use TinyBlocks\HttpQuery\Clause\Filters;
use TinyBlocks\HttpQuery\Clause\SeekClause;
use TinyBlocks\HttpQuery\Clause\SortClause;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Cursor\Keyset;

/** @var Keyset $keyset */
/** @var list<Comparison> $comparisons The validated comparisons read from Criteria::comparisons(). */
$columns = FilterColumns::create()
    ->plain(field: 'status', column: 'pay.status')
    ->plain(field: 'created_at', column: 'pay.created_at')
    ->wrapped(field: 'id', column: 'pay.id', binding: 'UUID_TO_BIN(%s)');

# Filters renders the comparisons, SeekClause renders the keyset predicate, both SqlClause.
$predicate = Every::of(
    Filters::from(columns: $columns, comparisons: $comparisons),
    SeekClause::from(keyset: $keyset, columns: $columns)
);

$sort = SortClause::from(orders: $keyset->orders(), columns: $columns);
$limit = $keyset->limit()->plusOne();

$where = $predicate->isEmpty() ? '' : sprintf(' WHERE %s', $predicate->sql());
$sql = sprintf('%s%s ORDER BY %s LIMIT %d', $base, $where, $sort->sql(), $limit->toInteger());
# Bind $predicate->parameters() and run $sql against your store.
```

`Clause\Every::of` combines several `SqlClause` predicates, dropping the empty ones, joining the rest with `AND`, and
merging their parameters. It is itself a `SqlClause`, so a consumer-owned predicate (for example a tenant scope) drops
into the same combination by implementing `SqlClause`.

`Filters::from` renders a flat list of comparisons joined with `AND`, which fits the default conjunction-only contract.
When the schema calls `allowDisjunction`, the filter may be an OR group or a nested group, so the consumer renders the
full tree with `Filters::fromTree(filter: $criteria->filter(), columns: $columns)` instead. It walks the tree, joins
each group by its own connective, wraps every group in parentheses, and threads the placeholder offsets across the whole
tree.

`Limit` is the page size value object. `plus` raises it by an amount and `plusOne` raises it by one, the extra row a
keyset page fetches to detect a next page. `toInteger` reads the value back.

`Filters::from` renders each comparison with the built-in operator mapping (`==`, `!=`, `=in=`, `=out=`, `=lt=`, `=gt=`,
`=le=`, `=ge=`, `=sw=`). To render a shape the built-in mapping does not cover, pass a `Clause\OperatorRenderer`. The
first renderer that `supports` a comparison's operator renders it, otherwise the built-in mapping does.

```php
<?php

declare(strict_types=1);

use TinyBlocks\HttpQuery\Clause\FilterColumn;
use TinyBlocks\HttpQuery\Clause\Fragment;
use TinyBlocks\HttpQuery\Clause\OperatorRenderer;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Operator;

final readonly class CaseInsensitiveContains implements OperatorRenderer
{
    public function render(FilterColumn $column, int $offset, Comparison $comparison): Fragment
    {
        $name = sprintf('filter_%d', $offset);
        $bind = sprintf($column->binding(), sprintf(':%s', $name));

        return Fragment::of(
            sql: sprintf('LOWER(%s) LIKE LOWER(%s)', $column->column(), $bind),
            parameters: [$name => sprintf('%%%s%%', $comparison->firstValue())]
        );
    }

    public function supports(Operator $operator): bool
    {
        return $operator === Operator::EQUAL;
    }
}

# Filters::from($columns, $comparisons, new CaseInsensitiveContains());
```

A custom renderer reuses an existing RSQL operator token (the grammar the `Scanner` accepts is fixed), so a comparison
the parser already produces is rendered differently. It does not add a new token to the filter grammar.

### Rendering navigation links

`toResponse` renders a result as a JSON:API response in one call. It builds the body from the data, the meta, and the
navigation links, and folds the same relations into an RFC 8288 `Link` header. It returns a PSR-7 `ResponseInterface`.
The submitted filter and sort are preserved in every URI, and the `self` link is built from the page's own current
pagination, so an offset page renders an offset `self` and a cursor page renders a cursor `self`.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\HttpQuery\Offset\Criteria;
use TinyBlocks\HttpQuery\Schema;

$schema = Schema::create()->sortable(fields: ['created_at', 'id']);

# GET /v1/orders?filter=status==paid&sort=-created_at,id&page[number]=3&page[size]=20
/** @var ServerRequestInterface $request */
$criteria = Criteria::fromQuery(schema: $schema, request: $request);

/** @var iterable<mixed> $items */
$response = $criteria->page(total: 480, items: $items)->toResponse(baseUri: '/v1/orders');
```

The body carries the data, the meta, and the links, with the filter and the sort preserved in every URI. The two
approaches return different shapes, shown below.

An **offset** page exposes the full window (`total`, `total_pages`, `current_page`, `has_previous`) and a link for every
relation:

```json
{
    "data": [
        {
            "id": 4821,
            "status": "paid",
            "total": 12990,
            "created_at": "2026-01-29T14:05:00Z"
        },
        {
            "id": 4820,
            "status": "paid",
            "total": 8400,
            "created_at": "2026-01-29T13:51:00Z"
        }
    ],
    "meta": {
        "total": 480,
        "per_page": 20,
        "total_pages": 24,
        "current_page": 3,
        "has_next": true,
        "has_previous": true
    },
    "links": {
        "self": "/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=3&page[size]=20",
        "first": "/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=1&page[size]=20",
        "prev": "/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=2&page[size]=20",
        "next": "/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=4&page[size]=20",
        "last": "/v1/orders?filter=status==paid&sort=-created_at,id&page[number]=24&page[size]=20"
    }
}
```

```text
Link: </v1/orders?filter=status==paid&sort=-created_at,id&page[number]=3&page[size]=20>; rel="self",
      </v1/orders?filter=status==paid&sort=-created_at,id&page[number]=1&page[size]=20>; rel="first",
      </v1/orders?filter=status==paid&sort=-created_at,id&page[number]=2&page[size]=20>; rel="prev",
      </v1/orders?filter=status==paid&sort=-created_at,id&page[number]=4&page[size]=20>; rel="next",
      </v1/orders?filter=status==paid&sort=-created_at,id&page[number]=24&page[size]=20>; rel="last"
```

A **cursor** page is forward-only: `meta` carries only `has_next` and `per_page`, and `links` only `self` and `next`.
For `GET /v1/orders?filter=status==paid&sort=-created_at,id&page[cursor]=BS3RvKY4LqEjYD19mQ0mCpJ&page[size]=20`:

```json
{
    "data": [
        {
            "id": 4821,
            "status": "paid",
            "total": 12990,
            "created_at": "2026-01-29T14:05:00Z"
        },
        {
            "id": 4820,
            "status": "paid",
            "total": 8400,
            "created_at": "2026-01-29T13:51:00Z"
        }
    ],
    "meta": {
        "per_page": 20,
        "has_next": true
    },
    "links": {
        "self": "/v1/orders?filter=status==paid&sort=-created_at,id&page[cursor]=BS3RvKY4LqEjYD19mQ0mCpJ&page[size]=20",
        "next": "/v1/orders?filter=status==paid&sort=-created_at,id&page[cursor]=Pj9rZ0sB2xN7wK1dQmvY4La&page[size]=20"
    }
}
```

```text
Link: </v1/orders?filter=status==paid&sort=-created_at,id&page[cursor]=BS3RvKY4LqEjYD19mQ0mCpJ&page[size]=20>; rel="self",
      </v1/orders?filter=status==paid&sort=-created_at,id&page[cursor]=Pj9rZ0sB2xN7wK1dQmvY4La&page[size]=20>; rel="next"
```

The `links` keys are the canonical JSON:API relations, in the semantic order below. Unavailable relations are omitted,
so the first offset page carries no `prev` and the last carries no `next`, and an `Offset\Slice` omits `last` (it has no
total).

| Relation | Meaning            |
|----------|--------------------|
| `self`   | The current page.  |
| `first`  | The first page.    |
| `prev`   | The previous page. |
| `next`   | The next page.     |
| `last`   | The last page.     |

#### Adding your own meta contents

A page derives its own `meta` from the pagination. When the endpoint owns a counter the page cannot derive, an unread
total for example, `withMetadata` returns a copy carrying it. The value renders inside `meta`, so the response keeps the
single JSON:API envelope and the RFC 8288 `Link` header. Both pagination approaches expose it.

```php
<?php

declare(strict_types=1);

use TinyBlocks\HttpQuery\Cursor\Keyset;

/** @var Keyset $keyset */
/** @var iterable<array{id: int, created_at: string}> $items */
$response = $keyset->page(items: $items)
    ->withMetadata(metadata: ['unread_count' => 7])
    ->toResponse(baseUri: '/v1/notifications');
```

```json
{
    "meta": {
        "per_page": 20,
        "has_next": true,
        "unread_count": 7
    }
}
```

The pagination entries come first, and the supplied ones follow in the order they were given. A supplied key that
repeats a pagination key is discarded, so `withMetadata(metadata: ['per_page' => 99])` leaves `per_page` on the real
page size. Calling it more than once accumulates.

## FAQ

### 01. Why does the library never touch a data store?

The query of a collection has two halves: deciding what to fetch, and fetching it. This library owns only the first
half. It parses the request into typed specifications and renders the response navigation, leaving the consumer free to
apply the specifications to any store, SQL, a document database, or an in-memory list. Keeping the store out makes every
operation pure value-object math and keeps the library framework- and database-agnostic.

### 02. Why is the query validated at parse instead of by the consumer?

A filter or a sort enters the system at parse, so that is where it is validated. The endpoint declares the contract once
on the `Schema`, and `Criteria::fromQuery` returns an already-validated result. The consumer reads `comparisons()` and
`sort()` without flattening a tree, enforcing an allowlist, or rendering an expression. A request outside the contract
fails at the boundary with a dedicated `HttpQueryException`, never reaching the store.

### 03. Why RSQL for filtering instead of ad-hoc query parameters?

RSQL is a small, URI-safe grammar with a published reference, so the filter survives a query string without encoding and
the same expression reads the same on the client and the server. The library is conjunction-only for the consumer: a
single comparison or an AND group of comparisons flattens into the `list<Comparison>` the consumer applies. An OR group,
or a group nested inside another group, is rejected as an unsupported shape.

> Zdenek Jirutka, *RSQL / FIQL parser*.

### 04. Why are the two pagination approaches split into separate contexts?

The pagination approach is a fixed decision of the endpoint, not a runtime property of a request. A single specification
that infers the approach at runtime renders an inconsistent `self` link: the first page of a cursor feed would carry an
offset-style `self` while its `next` is cursor-style. Splitting the API into a `Cursor` context and an `Offset` context,
each complete and approach-only, with the common building blocks at the root, makes every `self` link consistent with
the page's own approach.

### 05. How does the library help guard against injection?

Field and operator names are validated against the `Schema` allowlist while parsing, so only declared identifiers ever
reach your store. Comparison and cursor values are returned as data for you to bind as parameters, and the library
builds no SQL. A cursor token is decoded only as a list of scalar values. Any unsafe character in the filter and sort
echoed into the `links` object and the `Link` header is percent-encoded. Binding the values is still your
responsibility.

## License

Http Query is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
