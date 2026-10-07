<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Cursor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\HttpQuery\Models\Query;
use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Cursor\Criteria;
use TinyBlocks\HttpQuery\Cursor\Page;
use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;
use TinyBlocks\HttpQuery\Exceptions\PageSizeOutOfRange;
use TinyBlocks\HttpQuery\Exceptions\SortIsRequired;
use TinyBlocks\HttpQuery\Operator;
use TinyBlocks\HttpQuery\Schema;
use TinyBlocks\HttpQuery\Sort;
use TinyBlocks\HttpQuery\ValueKind;

final class CriteriaTest extends TestCase
{
    public function testFromQueryWhenEmptyThenSortIsEmpty(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQueryWithDefaultSchema(request: $query);

        /** @Then the effective sort is empty */
        self::assertTrue($criteria->sort()->isEmpty());
    }

    public function testFromQueryWhenEmptyThenComparisonsAreEmpty(): void
    {
        /** @Given empty query parameters */
        $query = Query::from(parameters: []);

        /** @When building the criteria from the query */
        $criteria = Criteria::fromQueryWithDefaultSchema(request: $query);

        /** @Then there is no comparison */
        self::assertSame([], $criteria->comparisons());
    }

    public function testKeysetWhenBuiltWithoutCursorThenBuildsCursorPage(): void
    {
        /** @Given a schema declaring a default sort over the identifier */
        $schema = Schema::create()->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @And a criteria parsed from a request carrying a page size of two and no cursor */
        $criteria = Criteria::fromQuery(schema: $schema, request: Query::from(parameters: ['page' => ['size' => '2']]));

        /** @When building a cursor page through the keyset view over the array rows fetched */
        $page = $criteria->keyset()->page(items: [['id' => 10], ['id' => 20], ['id' => 30]]);

        /** @Then the result is a cursor page */
        self::assertInstanceOf(Page::class, $page);

        /** @And the items are trimmed to the page size */
        self::assertSame([['id' => 10], ['id' => 20]], $page->items()->toArray());
    }

    public function testKeysetWhenIncomingCursorPresentThenBuildsCursorPage(): void
    {
        /** @Given an opaque token produced from ordering key values */
        $token = Token::fromKeys(keys: [5])->toString();

        /** @And a schema declaring a default sort over the identifier */
        $schema = Schema::create()->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @And a criteria parsed from a request carrying that cursor and a page size of two */
        $criteria = Criteria::fromQuery(
            schema: $schema,
            request: Query::from(parameters: ['page' => ['cursor' => $token, 'size' => '2']])
        );

        /** @When building a cursor page through the keyset view over the items fetched */
        $page = $criteria->keyset()->page(items: [10, 20, 30], keysOf: static fn(int $element): array => [$element]);

        /** @Then the cursor page reports a next page */
        self::assertTrue($page->hasNext());

        /** @And the items are trimmed to the page size */
        self::assertSame([10, 20], $page->items()->toArray());
    }

    public function testKeysetWhenEffectiveSortIsEmptyThenThrowsSortIsRequired(): void
    {
        /** @Given a criteria parsed from a request carrying no sort and no schema default */
        $criteria = Criteria::fromQueryWithDefaultSchema(request: Query::from(parameters: []));

        /** @Then an exception indicating a deterministic order is required is raised */
        $this->expectException(SortIsRequired::class);
        $this->expectExceptionMessage('A keyset requires a deterministic order, but the effective sort is empty.');

        /** @When building the keyset view */
        $criteria->keyset();
    }

    public function testFromQueryWhenCursorValueFitsDeclaredKindThenKeysetCarriesIt(): void
    {
        /** @Given an opaque token carrying a store timestamp and a UUID identifier */
        $token = Token::fromKeys(keys: ['2026-01-15 10:30:00.0', '01900000-0000-7099-8000-000000000001'])->toString();

        /** @And a schema declaring only the identifier cursor key as a UUID */
        $schema = Schema::create()
            ->cursorKey(field: 'id', valueKind: ValueKind::UUID)
            ->defaultSort(sort: Sort::fromExpression(expression: '-created_at,-id'));

        /** @When building the keyset view from a request carrying that cursor */
        $keyset = Criteria::fromQuery(
            schema: $schema,
            request: Query::from(parameters: ['page' => ['cursor' => $token]])
        )->keyset();

        /** @Then the keyset carries both values, the undeclared timestamp left unchecked */
        self::assertSame(
            ['created_at' => '2026-01-15 10:30:00.0', 'id' => '01900000-0000-7099-8000-000000000001'],
            $keyset->cursor()
        );
    }

    public function testFromQueryWhenCursorValueIsNullThenTheDeclaredKindLetsItPass(): void
    {
        /** @Given an opaque token carrying a timestamp and a null identifier */
        $token = Token::fromKeys(keys: ['2026-01-15T10:30:00Z', null])->toString();

        /** @And a schema declaring the identifier cursor key as a UUID */
        $schema = Schema::create()
            ->cursorKey(field: 'id', valueKind: ValueKind::UUID)
            ->defaultSort(sort: Sort::fromExpression(expression: '-created_at,-id'));

        /** @When building the keyset view from a request carrying that cursor */
        $keyset = Criteria::fromQuery(
            schema: $schema,
            request: Query::from(parameters: ['page' => ['cursor' => $token]])
        )->keyset();

        /** @Then the keyset carries the null value, which holds no key to check */
        self::assertSame(['created_at' => '2026-01-15T10:30:00Z', 'id' => null], $keyset->cursor());
    }

    public function testFromQueryWhenCustomSchemaGivenThenAppliesItsDefaultPageSize(): void
    {
        /** @Given a schema lowering the default page size and declaring a default sort */
        $schema = Schema::create()
            ->defaultPerPage(defaultPerPage: 5)
            ->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @When building the keyset view from a query carrying no page size and the schema */
        $keyset = Criteria::fromQuery(schema: $schema, request: Query::from(parameters: []))->keyset();

        /** @Then the keyset carries the schema default page size */
        self::assertSame(5, $keyset->limit()->toInteger());
    }

    public function testFromQueryWhenNoCursorThenDeclaredKindsLeaveTheFirstPageOpen(): void
    {
        /** @Given a schema declaring the identifier cursor key as a UUID */
        $schema = Schema::create()
            ->cursorKey(field: 'id', valueKind: ValueKind::UUID)
            ->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @When building the keyset view from a request carrying no cursor */
        $keyset = Criteria::fromQuery(schema: $schema, request: Query::from(parameters: []))->keyset();

        /** @Then every cursor key is null, so the first page is served */
        self::assertSame(['id' => null], $keyset->cursor());
    }

    public function testFromQueryWhenCursorPresentThenKeysetCarriesPageSizeAndCursor(): void
    {
        /** @Given an opaque token produced from a single ordering key value */
        $token = Token::fromKeys(keys: [5])->toString();

        /** @And a schema declaring a default sort over the identifier */
        $schema = Schema::create()->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @And a criteria parsed from a request carrying that cursor and a page size of ten */
        $criteria = Criteria::fromQuery(
            schema: $schema,
            request: Query::from(parameters: ['page' => ['cursor' => $token, 'size' => '10']])
        );

        /** @When building the keyset view */
        $keyset = $criteria->keyset();

        /** @Then the keyset carries the requested page size */
        self::assertSame(10, $keyset->limit()->toInteger());

        /** @And the keyset decodes the incoming cursor keyed by the sort field */
        self::assertSame(['id' => 5], $keyset->cursor());
    }

    #[DataProvider('schemaCopies')]
    public function testFromQueryWhenKindDeclaredBeforeAnotherCopyThenTheCopyKeepsIt(Schema $schema): void
    {
        /** @Given a schema copy derived after the identifier cursor key was declared as a UUID */

        /** @And an opaque token whose identifier value is not a UUID */
        $token = Token::fromKeys(keys: ['not-a-uuid'])->toString();

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage('does not match the uuid kind for cursor key <id>.');

        /** @When building the criteria from that copy sorted by the identifier and a request carrying the cursor */
        Criteria::fromQuery(
            schema: $schema->defaultSort(sort: Sort::fromExpression(expression: 'id')),
            request: Query::from(parameters: ['page' => ['cursor' => $token]])
        );
    }

    public function testFromQueryWhenPerPageAboveMaximumThenThrowsPageSizeOutOfRange(): void
    {
        /** @Given query parameters carrying a page size above the default maximum */
        $query = Query::from(parameters: ['page' => ['size' => '500']]);

        /** @Then an exception indicating the page size is out of range is raised */
        $this->expectException(PageSizeOutOfRange::class);
        $this->expectExceptionMessage('Page size');

        /** @When building the criteria from the query */
        Criteria::fromQueryWithDefaultSchema(request: $query);
    }

    public function testFromQueryWhenFilterAndSortGivenThenEachSpecificationIsValidated(): void
    {
        /** @Given a schema allowing the filtered and sorted fields */
        $schema = Schema::create()
            ->sortable(fields: ['created_at'])
            ->filterable(field: 'status', operators: [Operator::EQUAL]);

        /** @And a query carrying a filter and a sort */
        $query = Query::from(parameters: ['sort' => '-created_at', 'filter' => 'status==paid']);

        /** @When building the criteria from the query and the schema */
        $criteria = Criteria::fromQuery(schema: $schema, request: $query);

        /** @Then the validated comparisons carry the filtered field and value */
        self::assertEquals(
            [Comparison::of(field: 'status', values: ['paid'], operator: Operator::EQUAL)],
            $criteria->comparisons()
        );

        /** @And the effective sort is the client sort */
        self::assertEquals(Sort::fromExpression(expression: '-created_at'), $criteria->sort());
    }

    public function testFromQueryWhenForgedDatetimeCursorValueThenThrowsCursorIsInvalid(): void
    {
        /** @Given an opaque token whose creation timestamp value is not a date-time */
        $token = Token::fromKeys(keys: ['garbage', '01900000-0000-7099-8000-000000000001'])->toString();

        /** @And a schema declaring the timestamp cursor key as a date-time and the identifier as a UUID */
        $schema = Schema::create()
            ->cursorKey(field: 'created_at', valueKind: ValueKind::DATETIME)
            ->cursorKey(field: 'id', valueKind: ValueKind::UUID)
            ->defaultSort(sort: Sort::fromExpression(expression: '-created_at,-id'));

        /** @Then an exception naming the timestamp cursor key is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage('does not match the datetime kind for cursor key <created_at>.');

        /** @When building the criteria from a request carrying that cursor */
        Criteria::fromQuery(schema: $schema, request: Query::from(parameters: ['page' => ['cursor' => $token]]));
    }

    public function testFromQueryWhenIntegerCursorValueFitsIntegerKindThenKeysetCarriesIt(): void
    {
        /** @Given an opaque token carrying an integer identifier */
        $token = Token::fromKeys(keys: [5])->toString();

        /** @And a schema declaring the identifier cursor key as an integer */
        $schema = Schema::create()
            ->cursorKey(field: 'id', valueKind: ValueKind::INTEGER)
            ->defaultSort(sort: Sort::fromExpression(expression: 'id'));

        /** @When building the keyset view from a request carrying that cursor */
        $keyset = Criteria::fromQuery(
            schema: $schema,
            request: Query::from(parameters: ['page' => ['cursor' => $token]])
        )->keyset();

        /** @Then the keyset carries the integer value as decoded */
        self::assertSame(['id' => 5], $keyset->cursor());
    }

    public function testFromQueryWhenCursorValueBreaksDeclaredKindThenThrowsCursorIsInvalid(): void
    {
        /** @Given an opaque token whose identifier value is not a UUID */
        $token = Token::fromKeys(keys: ['2026-01-15 10:30:00.000000', 'not-a-uuid'])->toString();

        /** @And a schema declaring the identifier cursor key as a UUID and a default sort over both keys */
        $schema = Schema::create()
            ->cursorKey(field: 'id', valueKind: ValueKind::UUID)
            ->defaultSort(sort: Sort::fromExpression(expression: '-created_at,-id'));

        /** @And the reason template naming the token, the kind, and the cursor key */
        $template = 'Cursor token <%s> does not match the uuid kind for cursor key <id>.';

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage(sprintf($template, $token));

        /** @When building the criteria from a request carrying that cursor */
        Criteria::fromQuery(schema: $schema, request: Query::from(parameters: ['page' => ['cursor' => $token]]));
    }

    #[DataProvider('untypedCursorValues')]
    public function testFromQueryWhenCursorValueIsNeitherTextNorIntegerThenThrowsCursorIsInvalid(bool|float $key): void
    {
        /** @Given a cursor value that is neither text nor an integer */

        /** @And an opaque token carrying that value */
        $token = Token::fromKeys(keys: [$key])->toString();

        /** @And a schema declaring the name cursor key as a string */
        $schema = Schema::create()
            ->cursorKey(field: 'name', valueKind: ValueKind::STRING)
            ->defaultSort(sort: Sort::fromExpression(expression: 'name'));

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage('does not match the string kind for cursor key <name>.');

        /** @When building the criteria from a request carrying that cursor */
        Criteria::fromQuery(schema: $schema, request: Query::from(parameters: ['page' => ['cursor' => $token]]));
    }

    public static function schemaCopies(): array
    {
        $schema = Schema::create()->cursorKey(field: 'id', valueKind: ValueKind::UUID);

        return [
            'Sortable fields'   => ['schema' => $schema->sortable(fields: ['id'])],
            'Filterable field'  => ['schema' => $schema->filterable(field: 'status', operators: [Operator::EQUAL])],
            'Maximum page size' => ['schema' => $schema->maxPerPage(maxPerPage: 50)],
            'Default page size' => ['schema' => $schema->defaultPerPage(defaultPerPage: 10)],
            'Disjunction'       => ['schema' => $schema->allowDisjunction()]
        ];
    }

    public static function untypedCursorValues(): array
    {
        return [
            'Boolean value'  => ['key' => true],
            'Floating value' => ['key' => 1.5]
        ];
    }
}
