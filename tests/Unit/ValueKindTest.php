<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
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

    public static function matchingValues(): array
    {
        return [
            'Non-empty string'    => ['kind' => ValueKind::STRING, 'value' => 'paid'],
            'Positive integer'    => ['kind' => ValueKind::INTEGER, 'value' => '42'],
            'Negative integer'    => ['kind' => ValueKind::INTEGER, 'value' => '-7'],
            'Date only'           => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15'],
            'Date-time with zone' => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T10:30:00Z'],
            'Date-time offset'    => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T10:30:00+01:00'],
            'Date-time fraction'  => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T10:30:00.123Z']
        ];
    }

    public static function mismatchingValues(): array
    {
        return [
            'Empty string'         => ['kind' => ValueKind::STRING, 'value' => ''],
            'Non-numeric integer'  => ['kind' => ValueKind::INTEGER, 'value' => 'abc'],
            'Decimal integer'      => ['kind' => ValueKind::INTEGER, 'value' => '4.2'],
            'Free-form date'       => ['kind' => ValueKind::DATETIME, 'value' => 'not-a-date'],
            'Single-digit month'   => ['kind' => ValueKind::DATETIME, 'value' => '2023-1-15'],
            'Truncated date-time'  => ['kind' => ValueKind::DATETIME, 'value' => '2023-01-15T99']
        ];
    }
}
