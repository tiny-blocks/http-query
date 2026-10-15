<?php

declare(strict_types=1);

namespace TinyBlocks\HttpQuery\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a query parameter declared to be preserved in every navigation URI is malformed.
 *
 * A preserved parameter name must not be empty, must not carry a bracket, and must stay outside the
 * filter, sort, and page families the library renders itself. Its value must be a string or a nested
 * array of strings, the shapes a query string parses into. The message never carries the value.
 */
final class PreservedParameterIsInvalid extends InvalidArgumentException implements HttpQueryException
{
    private const string NAME_NOT_ALLOWED = 'Preserved parameter name <%s> is empty, bracketed, or reserved.';
    private const string VALUE_NOT_ALLOWED = 'Preserved parameter <%s> must be a string or a nested array of strings.';

    private function __construct(string $reason)
    {
        parent::__construct(message: $reason);
    }

    /**
     * Creates a PreservedParameterIsInvalid from the name that is empty, bracketed, or reserved.
     *
     * @param string $name The parameter name that cannot be preserved.
     * @return PreservedParameterIsInvalid The composed exception describing the rejected name.
     */
    public static function fromName(string $name): PreservedParameterIsInvalid
    {
        $template = PreservedParameterIsInvalid::NAME_NOT_ALLOWED;

        return new PreservedParameterIsInvalid(reason: sprintf($template, $name));
    }

    /**
     * Creates a PreservedParameterIsInvalid from the name whose value is not a string or nested strings.
     *
     * @param string $name The parameter name whose value cannot be preserved, the value kept out of the message.
     * @return PreservedParameterIsInvalid The composed exception describing the rejected value.
     */
    public static function fromValue(string $name): PreservedParameterIsInvalid
    {
        $template = PreservedParameterIsInvalid::VALUE_NOT_ALLOWED;

        return new PreservedParameterIsInvalid(reason: sprintf($template, $name));
    }
}
