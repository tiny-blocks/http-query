<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Clause;

use PHPUnit\Framework\TestCase;
use TinyBlocks\HttpQuery\Clause\FilterColumn;
use TinyBlocks\HttpQuery\Clause\FilterColumns;
use TinyBlocks\HttpQuery\Exceptions\FilterColumnNotMapped;

final class FilterColumnsTest extends TestCase
{
    public function testWithThenMapsTheGivenColumn(): void
    {
        /** @Given a mapping extended with an explicit column */
        $columns = FilterColumns::create()->with(field: 'status', column: FilterColumn::plain(column: 'pay.status'));

        /** @Then the field resolves to that column */
        self::assertSame('pay.status', $columns->for(field: 'status')->column());
    }

    public function testPlainThenMapsToThePlainColumn(): void
    {
        /** @Given a mapping carrying a plain field */
        $columns = FilterColumns::create()->plain(field: 'status', column: 'pay.status');

        /** @Then the field resolves to the column with a passthrough binding */
        self::assertSame('pay.status', $columns->for(field: 'status')->column());
        self::assertSame('%s', $columns->for(field: 'status')->binding());
    }

    public function testForWhenFieldIsAbsentThenThrows(): void
    {
        /** @Given an empty mapping */
        $columns = FilterColumns::create();

        /** @Then an exception indicating the field has no column mapping should be thrown */
        $this->expectException(FilterColumnNotMapped::class);
        $this->expectExceptionMessage('Filter field <status> has no column mapping.');

        /** @When the absent field is resolved */
        $columns->for(field: 'status');
    }

    public function testHasWhenFieldIsMappedThenReturnsTrue(): void
    {
        /** @Given a mapping carrying a field */
        $columns = FilterColumns::create()->plain(field: 'status', column: 'pay.status');

        /** @Then the field is reported present */
        self::assertTrue($columns->has(field: 'status'));
    }

    public function testHasWhenFieldIsAbsentThenReturnsFalse(): void
    {
        /** @Given an empty mapping */
        $columns = FilterColumns::create();

        /** @Then the field is reported absent */
        self::assertFalse($columns->has(field: 'status'));
    }

    public function testWrappedThenMapsWrappingThePlaceholder(): void
    {
        /** @Given a mapping carrying a wrapped field */
        $columns = FilterColumns::create()->wrapped(
            field: 'id',
            column: 'pay.id',
            binding: 'UUID_TO_BIN(%s)'
        );

        /** @Then the field resolves to the column with the wrapping binding */
        self::assertSame('pay.id', $columns->for(field: 'id')->column());
        self::assertSame('UUID_TO_BIN(%s)', $columns->for(field: 'id')->binding());
    }

    public function testBooleanThenNormalizesTheValueToAnInteger(): void
    {
        /** @Given a mapping carrying a boolean field */
        $columns = FilterColumns::create()->boolean(field: 'is_active', column: 'pme.is_active');

        /** @Then the field resolves to the column and normalizes true to one */
        self::assertSame('pme.is_active', $columns->for(field: 'is_active')->column());
        self::assertSame(1, $columns->for(field: 'is_active')->normalize('true'));
    }
}
