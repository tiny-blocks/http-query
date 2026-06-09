<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use TinyBlocks\HttpQuery\Internal\Rendering;

final class RenderingTest extends TestCase
{
    public function testConstructorWhenInvokedThroughReflectionThenInstantiatesTheStaticOnlyAssembler(): void
    {
        /** @Given an uninitialized instance of the static-only response assembler */
        $assembler = new ReflectionClass(Rendering::class)->newInstanceWithoutConstructor();

        /** @When invoking its otherwise-uncallable private constructor */
        new ReflectionMethod(Rendering::class, '__construct')->invoke($assembler);

        /** @Then the static-only response assembler is instantiated */
        self::assertInstanceOf(Rendering::class, $assembler);
    }
}
