<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use TinyBlocks\HttpQuery\Internal\DecimalInteger;
use TinyBlocks\HttpQuery\Internal\Iso8601;
use TinyBlocks\HttpQuery\ValueKind;

final class ValueKindTest extends TestCase
{
    #[DataProvider('matchingValues')]
    public function testMatchesWhenValueFitsTheKindThenIsTrue(ValueKind $kind, string $value): void
    {
        /** @Given a value kind and a value that fits it */

        /** @When testing the value against the kind */
        $matches = $kind->matches(value: $value);

        /** @Then it reports that the value matches the kind */
        self::assertTrue($matches);
    }

    #[DataProvider('mismatchingValues')]
    public function testMatchesWhenValueBreaksTheKindThenIsFalse(ValueKind $kind, string $value): void
    {
        /** @Given a value kind and a value that breaks it */

        /** @When testing the value against the kind */
        $matches = $kind->matches(value: $value);

        /** @Then it reports that the value does not match the kind */
        self::assertFalse($matches);
    }

    public function testConstructorWhenInvokedThroughReflectionThenInstantiatesTheStaticOnlyValidator(): void
    {
        /** @Given an uninitialized instance of the static-only ISO-8601 validator */
        $validator = new ReflectionClass(Iso8601::class)->newInstanceWithoutConstructor();

        /** @When invoking its otherwise-uncallable private constructor */
        new ReflectionMethod(Iso8601::class, '__construct')->invoke($validator);

        /** @Then the static-only ISO-8601 validator is instantiated */
        self::assertInstanceOf(Iso8601::class, $validator);
    }

    public function testConstructorWhenInvokedThroughReflectionThenInstantiatesTheStaticOnlyIntegerValidator(): void
    {
        /** @Given an uninitialized instance of the static-only decimal integer validator */
        $validator = new ReflectionClass(DecimalInteger::class)->newInstanceWithoutConstructor();

        /** @When invoking its otherwise-uncallable private constructor */
        new ReflectionMethod(DecimalInteger::class, '__construct')->invoke($validator);

        /** @Then the static-only decimal integer validator is instantiated */
        self::assertInstanceOf(DecimalInteger::class, $validator);
    }

    public static function matchingValues(): array
    {
        return [
            'Lowercase UUID'              => [
                'kind'  => ValueKind::UUID,
                'value' => '01900000-0000-7099-8000-000000000001'
            ],
            'Uppercase UUID'              => [
                'kind'  => ValueKind::UUID,
                'value' => '0190A3F2-1B2C-7D4E-8F60-123456789ABC'
            ],
            'Non-empty string'            => ['kind' => ValueKind::STRING, 'value' => 'paid'],
            'Zero'                        => ['kind' => ValueKind::INTEGER, 'value' => '0'],
            'Negative zero'               => ['kind' => ValueKind::INTEGER, 'value' => '-0'],
            'Positive integer'            => ['kind' => ValueKind::INTEGER, 'value' => '42'],
            'Negative integer'            => ['kind' => ValueKind::INTEGER, 'value' => '-7'],
            'Zero-padded integer'         => ['kind' => ValueKind::INTEGER, 'value' => '0042'],
            'Largest integer'             => ['kind' => ValueKind::INTEGER, 'value' => '9223372036854775807'],
            'Smallest integer'            => ['kind' => ValueKind::INTEGER, 'value' => '-9223372036854775808'],
            'Padded largest integer'      => ['kind' => ValueKind::INTEGER, 'value' => '0009223372036854775807'],
            'Padded smallest integer'     => ['kind' => ValueKind::INTEGER, 'value' => '-009223372036854775808'],
            'Date only'                   => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15'],
            'Leap day'                    => ['kind' => ValueKind::DATETIME, 'value' => '2024-02-29'],
            'Earliest date'               => ['kind' => ValueKind::DATETIME, 'value' => '0001-01-01'],
            'Latest date'                 => ['kind' => ValueKind::DATETIME, 'value' => '9999-12-31'],
            'Date-time without zone'      => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T10:30:00'],
            'Date-time with zone'         => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T10:30:00Z'],
            'Date-time offset'            => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T10:30:00+01:00'],
            'Date-time negative offset'   => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T10:30:00-03:00'],
            'Date-time largest offset'    => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T10:30:00+23:59'],
            'Date-time fraction'          => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T10:30:00.123Z'],
            'Date-time nanoseconds'       => [
                'kind'  => ValueKind::DATETIME,
                'value' => '2023-01-15T10:30:00.123456789Z'
            ],
            'Last second of a day'        => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T23:59:59Z'],
            'Earliest instant'            => ['kind' => ValueKind::DATETIME, 'value' => '0001-01-01T00:00:00Z'],
            'Earliest instant ahead'      => ['kind' => ValueKind::DATETIME, 'value' => '0001-01-01T01:00:00+01:00'],
            'Earliest instant half ahead' => ['kind' => ValueKind::DATETIME, 'value' => '0001-01-01T00:30:00+00:30'],
            'Earliest date behind UTC'    => ['kind' => ValueKind::DATETIME, 'value' => '0001-01-01T00:00:00-05:00'],
            'Latest instant'              => ['kind' => ValueKind::DATETIME, 'value' => '9999-12-31T23:59:59Z'],
            'Latest instant fraction'     => [
                'kind'  => ValueKind::DATETIME,
                'value' => '9999-12-31T23:59:59.999999999Z'
            ],
            'Latest instant behind'       => ['kind' => ValueKind::DATETIME, 'value' => '9999-12-31T18:59:59-05:00'],
            'Latest instant half behind'  => ['kind' => ValueKind::DATETIME, 'value' => '9999-12-31T23:29:00-00:30'],
            'Latest instant zero offset'  => ['kind' => ValueKind::DATETIME, 'value' => '9999-12-31T23:59:59-00:00'],
            'Latest date ahead of UTC'    => ['kind' => ValueKind::DATETIME, 'value' => '9999-12-31T23:59:59+05:00']
        ];
    }

    public static function mismatchingValues(): array
    {
        return [
            'Free-form identifier'         => ['kind' => ValueKind::UUID, 'value' => 'not-a-uuid'],
            'UUID without hyphens'         => [
                'kind'  => ValueKind::UUID,
                'value' => '01900000000070998000000000000001'
            ],
            'UUID leading text'            => [
                'kind'  => ValueKind::UUID,
                'value' => 'x01900000-0000-7099-8000-000000000001'
            ],
            'UUID trailing text'           => [
                'kind'  => ValueKind::UUID,
                'value' => '01900000-0000-7099-8000-000000000001x'
            ],
            'UUID trailing newline'        => [
                'kind'  => ValueKind::UUID,
                'value' => "01900000-0000-7099-8000-000000000001\n"
            ],
            'UUID as URN'                  => [
                'kind'  => ValueKind::UUID,
                'value' => 'urn:uuid:01900000-0000-7099-8000-000000000001'
            ],
            'Non-hexadecimal UUID'         => [
                'kind'  => ValueKind::UUID,
                'value' => '0190000g-0000-7099-8000-000000000001'
            ],
            'Empty string'                 => ['kind' => ValueKind::STRING, 'value' => ''],
            'Non-numeric integer'          => ['kind' => ValueKind::INTEGER, 'value' => 'abc'],
            'Decimal integer'              => ['kind' => ValueKind::INTEGER, 'value' => '4.2'],
            'Sign without digits'          => ['kind' => ValueKind::INTEGER, 'value' => '-'],
            'Integer leading text'         => ['kind' => ValueKind::INTEGER, 'value' => 'x42'],
            'Integer trailing text'        => ['kind' => ValueKind::INTEGER, 'value' => '42x'],
            'Integer trailing newline'     => ['kind' => ValueKind::INTEGER, 'value' => "42\n"],
            'Integer above largest'        => ['kind' => ValueKind::INTEGER, 'value' => '9223372036854775808'],
            'Integer below smallest'       => ['kind' => ValueKind::INTEGER, 'value' => '-9223372036854775809'],
            'Integer with twenty digits'   => ['kind' => ValueKind::INTEGER, 'value' => '99999999999999999999'],
            'Padded integer above largest' => ['kind' => ValueKind::INTEGER, 'value' => '09223372036854775808'],
            'Twenty digits led by one'     => ['kind' => ValueKind::INTEGER, 'value' => '10000000000000000000'],
            'Free-form date'               => ['kind' => ValueKind::DATETIME, 'value' => 'not-a-date'],
            'Single-digit month'           => ['kind' => ValueKind::DATETIME, 'value' => '2023-1-15'],
            'Truncated date-time'          => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T99'],
            'Date leading text'            => ['kind' => ValueKind::DATETIME, 'value' => 'x2023-01-15'],
            'Date trailing newline'        => ['kind' => ValueKind::DATETIME, 'value' => "2023-01-15\n"],
            'Date-time trailing newline'   => ['kind' => ValueKind::DATETIME, 'value' => "2023-01-15T10:30:00Z\n"],
            'Impossible day of month'      => ['kind' => ValueKind::DATETIME, 'value' => '2026-02-30'],
            'Leap day of a common year'    => ['kind' => ValueKind::DATETIME, 'value' => '2023-02-29'],
            'Thirteenth month'             => ['kind' => ValueKind::DATETIME, 'value' => '2026-13-01'],
            'Month zero'                   => ['kind' => ValueKind::DATETIME, 'value' => '2026-00-10'],
            'Day zero'                     => ['kind' => ValueKind::DATETIME, 'value' => '2026-01-00'],
            'Day thirty-two'               => ['kind' => ValueKind::DATETIME, 'value' => '2026-01-32'],
            'Year zero'                    => ['kind' => ValueKind::DATETIME, 'value' => '0000-01-01'],
            'Year zero date-time'          => ['kind' => ValueKind::DATETIME, 'value' => '0000-12-31T23:59:59Z'],
            'Hour twenty-four'             => ['kind' => ValueKind::DATETIME, 'value' => '2026-05-27T24:00:00Z'],
            'Hour ninety-nine'             => ['kind' => ValueKind::DATETIME, 'value' => '2026-05-27T99:00:00Z'],
            'Minute sixty'                 => ['kind' => ValueKind::DATETIME, 'value' => '2026-05-27T10:60:00Z'],
            'Second sixty'                 => ['kind' => ValueKind::DATETIME, 'value' => '2026-05-27T10:00:60Z'],
            'Offset hour twenty-four'      => ['kind' => ValueKind::DATETIME, 'value' => '2026-05-27T10:00:00+24:00'],
            'Offset minute sixty'          => ['kind' => ValueKind::DATETIME, 'value' => '2026-05-27T10:00:00+01:60'],
            'Largest offset minute sixty'  => ['kind' => ValueKind::DATETIME, 'value' => '2026-05-27T10:00:00+23:60'],
            'Offset without colon'         => ['kind' => ValueKind::DATETIME, 'value' => '2026-05-27T10:00:00+0100'],
            'Fraction without digits'      => ['kind' => ValueKind::DATETIME, 'value' => '2026-05-27T10:00:00.Z'],
            'Fraction with ten digits'     => [
                'kind'  => ValueKind::DATETIME,
                'value' => '2026-05-27T10:00:00.1234567890Z'
            ],
            'Before earliest instant'      => ['kind' => ValueKind::DATETIME, 'value' => '0001-01-01T00:59:00+01:00'],
            'Before earliest half ahead'   => ['kind' => ValueKind::DATETIME, 'value' => '0001-01-01T00:29:59+00:30'],
            'After latest instant'         => ['kind' => ValueKind::DATETIME, 'value' => '9999-12-31T22:00:00-05:00'],
            'After latest half behind'     => ['kind' => ValueKind::DATETIME, 'value' => '9999-12-31T23:30:00-00:30']
        ];
    }
}
