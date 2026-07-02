<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Rsql;

use TinyBlocks\HttpQuery\Comparison;
use TinyBlocks\HttpQuery\Exceptions\FilterExpressionIsInvalid;
use TinyBlocks\HttpQuery\Filter;
use TinyBlocks\HttpQuery\Group;
use TinyBlocks\HttpQuery\LogicalOperator;

final readonly class FilterParser
{
    private const int MAX_DEPTH = 32;

    private function __construct(private string $input, private Scanner $scanner)
    {
    }

    public static function from(string $input): FilterParser
    {
        return new FilterParser(input: $input, scanner: Scanner::from(input: $input));
    }

    public function parse(): Filter
    {
        $filter = $this->disjunction(depth: 0);

        if (!$this->scanner->isAtEnd()) {
            throw FilterExpressionIsInvalid::from(expression: $this->input);
        }

        return $filter;
    }

    private function comparison(): Comparison
    {
        $field = $this->scanner->unreserved();
        $operator = $this->scanner->operator();

        if ($this->scanner->peek() !== '(') {
            return Comparison::of(field: $field, values: [$this->scanner->value()], operator: $operator);
        }

        $this->scanner->expect(character: '(');
        $values = [$this->scanner->value()];

        while ($this->scanner->peek() === ',') {
            $this->scanner->expect(character: ',');
            $values[] = $this->scanner->value();
        }

        $this->scanner->expect(character: ')');

        return Comparison::of(field: $field, values: $values, operator: $operator);
    }

    private function constraint(int $depth): Filter
    {
        if ($this->scanner->peek() === '(') {
            if ($depth >= FilterParser::MAX_DEPTH) {
                throw FilterExpressionIsInvalid::from(expression: $this->input);
            }

            $this->scanner->expect(character: '(');
            $group = $this->disjunction(depth: ($depth + 1));
            $this->scanner->expect(character: ')');

            return $group;
        }

        return $this->comparison();
    }

    private function conjunction(int $depth): Filter
    {
        $filters = [$this->constraint(depth: $depth)];

        while ($this->scanner->peek() === LogicalOperator::AND->value) {
            $this->scanner->expect(character: LogicalOperator::AND->value);
            $filters[] = $this->constraint(depth: $depth);
        }

        return count($filters) === 1 ? $filters[0] : Group::of(filters: $filters, operator: LogicalOperator::AND);
    }

    private function disjunction(int $depth): Filter
    {
        $filters = [$this->conjunction(depth: $depth)];

        while ($this->scanner->peek() === LogicalOperator::OR->value) {
            $this->scanner->expect(character: LogicalOperator::OR->value);
            $filters[] = $this->conjunction(depth: $depth);
        }

        return count($filters) === 1 ? $filters[0] : Group::of(filters: $filters, operator: LogicalOperator::OR);
    }
}
