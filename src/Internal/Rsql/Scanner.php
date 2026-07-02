<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Internal\Rsql;

use TinyBlocks\HttpQuery\Exceptions\FilterExpressionIsInvalid;
use TinyBlocks\HttpQuery\Operator;

final class Scanner
{
    private const string RESERVED = '"\'();,=!~<>';

    private int $position = 0;

    private function __construct(private readonly string $input)
    {
    }

    public static function from(string $input): Scanner
    {
        return new Scanner(input: $input);
    }

    public function peek(): string
    {
        return ($this->input[$this->position] ?? '');
    }

    public function value(): string
    {
        if ($this->peek() === '"') {
            return $this->quoted(quote: '"');
        }

        if ($this->peek() === "'") {
            return $this->quoted(quote: "'");
        }

        return $this->unreserved();
    }

    public function expect(string $character): void
    {
        if ($this->peek() !== $character) {
            throw FilterExpressionIsInvalid::from(expression: $this->input);
        }

        $this->position++;
    }

    private function quoted(string $quote): string
    {
        $this->position++;
        $characters = [];

        while ($this->position < strlen($this->input)) {
            $character = $this->input[$this->position];

            if ($character === '\\') {
                $characters[] = ($this->input[($this->position + 1)] ?? '');
                $this->position += 2;
                continue;
            }

            if ($character === $quote) {
                $this->position++;
                return implode('', $characters);
            }

            $characters[] = $character;
            $this->position++;
        }

        throw FilterExpressionIsInvalid::from(expression: $this->input);
    }

    public function isAtEnd(): bool
    {
        return $this->position === strlen($this->input);
    }

    public function operator(): Operator
    {
        foreach (Operator::cases() as $operator) {
            $token = $operator->value;

            if (substr($this->input, $this->position, strlen($token)) === $token) {
                $this->position += strlen($token);
                return $operator;
            }
        }

        throw FilterExpressionIsInvalid::from(expression: $this->input);
    }

    private function isReserved(string $character): bool
    {
        return $character === ' ' || str_contains(self::RESERVED, $character);
    }

    public function unreserved(): string
    {
        $start = $this->position;

        while ($this->position < strlen($this->input) && !$this->isReserved(character: $this->input[$this->position])) {
            $this->position++;
        }

        $token = substr($this->input, $start, ($this->position - $start));

        if ($token === '') {
            throw FilterExpressionIsInvalid::from(expression: $this->input);
        }

        return $token;
    }
}
