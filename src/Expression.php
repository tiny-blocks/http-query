<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

/**
 * RSQL expression a filter renders to, aware of the connective that produced it.
 */
final readonly class Expression
{
    private function __construct(private string $value, private ?LogicalOperator $connective)
    {
    }

    /**
     * Creates an atomic Expression from a rendered leaf.
     *
     * @param string $value The rendered leaf.
     * @return Expression The atomic expression.
     */
    public static function atomic(string $value): Expression
    {
        return new Expression(value: $value, connective: null);
    }

    /**
     * Creates a grouped Expression from rendered children and the connective joining them.
     *
     * @param string $value The rendered children joined by the connective.
     * @param LogicalOperator $connective The connective that produced the expression.
     * @return Expression The grouped expression.
     */
    public static function grouped(string $value, LogicalOperator $connective): Expression
    {
        return new Expression(value: $value, connective: $connective);
    }

    /**
     * Returns the RSQL expression.
     *
     * @return string The RSQL expression.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Returns the expression parenthesized when it binds looser than the given connective.
     *
     * @param LogicalOperator $parent The connective the expression is nested within.
     * @return string The expression, parenthesized when required.
     */
    public function nestedWithin(LogicalOperator $parent): string
    {
        if (!is_null($this->connective) && $parent->bindsTighterThan(other: $this->connective)) {
            $template = '(%s)';

            return sprintf($template, $this->value);
        }

        return $this->value;
    }
}
