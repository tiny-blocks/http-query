<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use TinyBlocks\Encoder\Base62;
use TinyBlocks\HttpQuery\Cursor;
use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;
use TinyBlocks\HttpQuery\Internal\Cursor\CursorCodec;

final class CursorTest extends TestCase
{
    public function testFromKeysWhenKeysGivenThenIsPresent(): void
    {
        /** @Given a cursor backed by ordering key values */
        $cursor = Cursor::fromKeys(keys: ['x']);

        /** @When inspecting whether the cursor is absent */
        $isAbsent = $cursor->isAbsent();

        /** @Then it reports itself as present */
        self::assertFalse($isAbsent);
    }

    public function testFromWhenEmptyTokenGivenThenIsAbsent(): void
    {
        /** @Given a cursor backed by an empty incoming token */
        $cursor = Cursor::from(token: '');

        /** @When inspecting whether the cursor is absent */
        $isAbsent = $cursor->isAbsent();

        /** @Then it reports itself as absent */
        self::assertTrue($isAbsent);
    }

    public function testFromWhenNonEmptyTokenGivenThenIsPresent(): void
    {
        /** @Given a cursor backed by a non-empty incoming token */
        $cursor = Cursor::from(token: 'abc');

        /** @When inspecting whether the cursor is absent */
        $isAbsent = $cursor->isAbsent();

        /** @Then it reports itself as present */
        self::assertFalse($isAbsent);
    }

    public function testNoneThenAbsentWithEmptyKeysAndEmptyToken(): void
    {
        /** @Given an absent cursor */
        $cursor = Cursor::none();

        /** @When inspecting the absent cursor */
        $isAbsent = $cursor->isAbsent();

        /** @Then it reports itself as absent */
        self::assertTrue($isAbsent);

        /** @And it decodes to no key values */
        self::assertSame([], $cursor->toArray());

        /** @And it renders an empty token */
        self::assertSame('', $cursor->toString());
    }

    public function testFromKeysWhenKeysGivenThenDecodesToTheSameKeys(): void
    {
        /** @Given a cursor backed by ordering key values */
        $cursor = Cursor::fromKeys(keys: ['2024-01-01', 42]);

        /** @When decoding the cursor into key values */
        $keys = $cursor->toArray();

        /** @Then it yields the original key values */
        self::assertSame(['2024-01-01', 42], $keys);
    }

    public function testFromKeysWhenKeysAreGappedThenDecodesToAReindexedList(): void
    {
        /** @Given a cursor backed by ordering key values held under gapped integer keys */
        $cursor = Cursor::fromKeys(keys: [5 => 'x', 9 => 'y']);

        /** @When decoding the cursor into key values */
        $keys = $cursor->toArray();

        /** @Then it yields the values as a zero-based list */
        self::assertSame(['x', 'y'], $keys);
    }

    public function testToArrayWhenTokenIsNotBase62ThenThrowsCursorIsInvalid(): void
    {
        /** @Given a cursor backed by a token carrying characters outside the base62 alphabet */
        $cursor = Cursor::from(token: 'not valid base62 !!!');

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage('could not be decoded');

        /** @When decoding the cursor into key values */
        $cursor->toArray();
    }

    public function testToArrayWhenTokenDecodesToScalarThenThrowsCursorIsInvalid(): void
    {
        /** @Given a base62 token whose decoded payload is the JSON scalar 5 */
        $cursor = Cursor::from(token: Base62::from(value: '5')->encode());

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage('could not be decoded');

        /** @When decoding the cursor into key values */
        $cursor->toArray();
    }

    public function testFromKeysWhenRoundTrippedThroughTokenThenYieldsTheOriginalKeys(): void
    {
        /** @Given the opaque token produced from ordering key values */
        $token = Cursor::fromKeys(keys: ['2024-01-01', 42])->toString();

        /** @And the token is not empty */
        self::assertNotSame('', $token);

        /** @When rebuilding a cursor from that token and decoding it */
        $keys = Cursor::from(token: $token)->toArray();

        /** @Then it yields the original key values */
        self::assertSame(['2024-01-01', 42], $keys);
    }

    public function testConstructorWhenInvokedThroughReflectionThenInstantiatesTheStaticOnlyCodec(): void
    {
        /** @Given an uninitialized instance of the static-only cursor codec */
        $codec = new ReflectionClass(CursorCodec::class)->newInstanceWithoutConstructor();

        /** @When invoking its otherwise-uncallable private constructor */
        new ReflectionMethod(CursorCodec::class, '__construct')->invoke($codec);

        /** @Then the static-only cursor codec is instantiated */
        self::assertInstanceOf(CursorCodec::class, $codec);
    }
}
