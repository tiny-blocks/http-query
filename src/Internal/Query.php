<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal;

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\Http\Attribute;
use TinyBlocks\Http\Server\Decoded\QueryParameters;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\Internal\Rsql\FilterParser;
use TinyBlocks\HttpQuery\Schema;
use TinyBlocks\HttpQuery\Sort;

final readonly class Query
{
    private function __construct(
        private Sort $sort,
        private Filter $filter,
        private int $pageSize,
        private int $pageNumber,
        private array $comparisons,
        private string $cursorToken,
        private Sort $submittedSort
    ) {
    }

    public static function from(Schema $schema, ServerRequestInterface $request): Query
    {
        $parameters = QueryParameters::from(request: $request);
        $page = $parameters->get(key: 'page')->toArray();

        $size = Attribute::from(value: $page['size'] ?? null);
        $cursor = Attribute::from(value: $page['cursor'] ?? null);
        $number = Attribute::from(value: $page['number'] ?? null);

        $expression = $parameters->get(key: 'filter')->toString();
        $filter = $expression === '' ? Group::none() : FilterParser::from(input: $expression)->parse();
        $submittedSort = Sort::fromExpression(expression: $parameters->get(key: 'sort')->toString());

        return new Query(
            sort: $schema->sortFor(sort: $submittedSort),
            filter: $filter,
            pageSize: $schema->pageSizeFor(requested: $size->toString() === '' ? null : $size->toInteger()),
            pageNumber: $number->toString() === '' ? 1 : $number->toInteger(),
            comparisons: $schema->comparisonsFor(filter: $filter, expression: $expression),
            cursorToken: $cursor->toString(),
            submittedSort: $submittedSort
        );
    }

    public function sort(): Sort
    {
        return $this->sort;
    }

    public function filter(): Filter
    {
        return $this->filter;
    }

    public function pageSize(): int
    {
        return $this->pageSize;
    }

    public function pageNumber(): int
    {
        return $this->pageNumber;
    }

    public function comparisons(): array
    {
        return $this->comparisons;
    }

    public function cursorToken(): string
    {
        return $this->cursorToken;
    }

    public function submittedSort(): Sort
    {
        return $this->submittedSort;
    }
}
