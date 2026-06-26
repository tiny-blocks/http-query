<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Rsql;

use TinyBlocks\HttpQuery\LogicalOperator;

final readonly class Expression
{
    private function __construct(private string $value, private ?LogicalOperator $connective)
    {
    }

    public static function atomic(string $value): Expression
    {
        return new Expression(value: $value, connective: null);
    }

    public static function grouped(string $value, LogicalOperator $connective): Expression
    {
        return new Expression(value: $value, connective: $connective);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function nestedWithin(LogicalOperator $parent): string
    {
        if (!is_null($this->connective) && $parent->bindsTighterThan(other: $this->connective)) {
            $template = '(%s)';

            return sprintf($template, $this->value);
        }

        return $this->value;
    }
}
