<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Exceptions\PreservedParameterIsInvalid;
use TinyBlocks\HttpQuery\PreservedParameters;

final class PreservedParametersTest extends TestCase
{
    public function testFromWhenEmptyListThenRendersNoPairForIt(): void
    {
        /** @Given an empty list next to a scalar parameter */
        $parameters = ['ids' => [], 'from' => '2026-10-01'];

        /** @When creating the preserved parameters from them */
        $actual = PreservedParameters::from(parameters: $parameters)->toQueryString();

        /** @Then only the scalar parameter renders */
        self::assertSame('from=2026-10-01', $actual);
    }

    public function testFromWhenNameIsNumericThenRendersItAsAName(): void
    {
        /** @Given a parameter whose name the query string parsed into an integer key */
        $parameters = [2026 => 'year'];

        /** @When creating the preserved parameters from it */
        $actual = PreservedParameters::from(parameters: $parameters)->toQueryString();

        /** @Then the numeric name renders as written */
        self::assertSame('2026=year', $actual);
    }

    public function testFromWhenNestedMapThenRendersBracketedKeys(): void
    {
        /** @Given a window parameter carrying a nested map of bounds */
        $parameters = ['window' => ['from' => '2026-10-01', 'to' => '2026-10-31']];

        /** @When creating the preserved parameters from it */
        $actual = PreservedParameters::from(parameters: $parameters)->toQueryString();

        /** @Then each bound renders under a bracketed key */
        self::assertSame('window[from]=2026-10-01&window[to]=2026-10-31', $actual);
    }

    public function testFromWhenListThenRendersIndexedBracketedKeys(): void
    {
        /** @Given a parameter carrying a list of identifiers */
        $parameters = ['ids' => ['10', '20']];

        /** @When creating the preserved parameters from it */
        $actual = PreservedParameters::from(parameters: $parameters)->toQueryString();

        /** @Then each identifier renders under its index */
        self::assertSame('ids[0]=10&ids[1]=20', $actual);
    }

    public function testFromWhenNoParameterThenRendersAnEmptyFragment(): void
    {
        /** @Given no parameter to preserve */
        $parameters = [];

        /** @When creating the preserved parameters from it */
        $actual = PreservedParameters::from(parameters: $parameters)->toQueryString();

        /** @Then the fragment is empty */
        self::assertSame('', $actual);
    }

    public function testFromWhenValueCarriesBracketsThenPercentEncodesThem(): void
    {
        /** @Given a value carrying brackets */
        $parameters = ['label' => '[draft]'];

        /** @When creating the preserved parameters from it */
        $actual = PreservedParameters::from(parameters: $parameters)->toQueryString();

        /** @Then the brackets in the value are percent-encoded */
        self::assertSame('label=%5Bdraft%5D', $actual);
    }

    public function testFromWhenDeeplyNestedMapThenRendersEveryLevelBracketed(): void
    {
        /** @Given a parameter nested two levels deep */
        $parameters = ['range' => ['start' => ['date' => '2026-10-01']]];

        /** @When creating the preserved parameters from it */
        $actual = PreservedParameters::from(parameters: $parameters)->toQueryString();

        /** @Then every level renders under its own bracketed key */
        self::assertSame('range[start][date]=2026-10-01', $actual);
    }

    #[DataProvider('invalidNameCases')]
    public function testFromWhenNameIsInvalidThenThrowsPreservedParameterIsInvalid(string $name): void
    {
        /** @Given a parameter name that is empty, bracketed, or reserved */

        /** @Then an exception indicating the preserved parameter is invalid is raised */
        $this->expectException(PreservedParameterIsInvalid::class);
        $this->expectExceptionMessage(
            sprintf('Preserved parameter name <%s> is empty, bracketed, or reserved.', $name)
        );

        /** @When creating the preserved parameters from it */
        PreservedParameters::from(parameters: [$name => 'value']);
    }

    public function testFromWhenValueCarriesReservedCharactersThenPercentEncodesThem(): void
    {
        /** @Given instants carrying a colon and a plus offset, next to a note carrying separators */
        $parameters = ['from' => '2026-10-01T09:00:00+00:00', 'note' => 'a&b=c #d'];

        /** @When creating the preserved parameters from them */
        $actual = PreservedParameters::from(parameters: $parameters)->toQueryString();

        /** @Then every reserved character in the values is percent-encoded */
        self::assertSame('from=2026-10-01T09%3A00%3A00%2B00%3A00&note=a%26b%3Dc%20%23d', $actual);
    }

    #[DataProvider('invalidValueCases')]
    public function testFromWhenValueIsNotAStringThenThrowsPreservedParameterIsInvalid(mixed $value): void
    {
        /** @Given a parameter value that is neither a string nor a nested array of strings */

        /** @Then an exception indicating the preserved parameter is invalid is raised */
        $this->expectException(PreservedParameterIsInvalid::class);
        $this->expectExceptionMessage('Preserved parameter <from> must be a string or a nested array of strings.');

        /** @When creating the preserved parameters from it */
        PreservedParameters::from(parameters: ['from' => $value]);
    }

    public function testFromWhenNameAndKeyCarryReservedCharactersThenPercentEncodesThem(): void
    {
        /** @Given a name and a nested key carrying a space and an ampersand */
        $parameters = ['time zone' => ['a&b' => 'UTC']];

        /** @When creating the preserved parameters from it */
        $actual = PreservedParameters::from(parameters: $parameters)->toQueryString();

        /** @Then the name and the key are percent-encoded around literal brackets */
        self::assertSame('time%20zone[a%26b]=UTC', $actual);
    }

    public static function invalidNameCases(): array
    {
        return [
            'Empty'               => ['name' => ''],
            'Page family'         => ['name' => 'page'],
            'Sort parameter'      => ['name' => 'sort'],
            'Filter parameter'    => ['name' => 'filter'],
            'Opening bracket'     => ['name' => 'window['],
            'Closing bracket'     => ['name' => 'window]'],
            'Bracketed path name' => ['name' => 'window[from]']
        ];
    }

    public static function invalidValueCases(): array
    {
        return [
            'Null'           => ['value' => null],
            'Integer'        => ['value' => 10],
            'Boolean'        => ['value' => true],
            'Nested integer' => ['value' => ['start' => 10]]
        ];
    }
}
