<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery;

use TinyBlocks\HttpQuery\Exceptions\PreservedParameterIsInvalid;
use TinyBlocks\HttpQuery\Internal\PreservedPairs;

/**
 * Query parameters outside filter, sort, and page that every navigation URI preserves.
 *
 * <p>Each parameter is a string or a nested array of strings, the shapes a query string parses
 * into. A nested array renders under bracketed keys, and every name, key, and value is
 * percent-encoded, so a reserved character in a value never splits or ends the query string.</p>
 */
final readonly class PreservedParameters
{
    private function __construct(private string $query)
    {
    }

    /**
     * Creates a PreservedParameters from the parameters keyed by name, kept in the given order.
     *
     * @param array<array-key, mixed> $parameters The parameters keyed by name, each a string or a nested array
     * of strings.
     * @return PreservedParameters The parameters every navigation URI preserves.
     * @throws PreservedParameterIsInvalid If a name is empty, bracketed, or reserved, or a value is not a string
     * or a nested array of strings.
     */
    public static function from(array $parameters): PreservedParameters
    {
        return new PreservedParameters(query: PreservedPairs::from(parameters: $parameters)->toQueryString());
    }

    /**
     * Returns the PreservedParameters as a query string fragment.
     *
     * @return string The fragment carrying every parameter, empty when there is none.
     */
    public function toQueryString(): string
    {
        return $this->query;
    }
}
