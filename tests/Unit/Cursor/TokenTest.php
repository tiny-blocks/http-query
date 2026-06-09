<?php

declare(strict_types=1);

namespace Test\TinyBlocks\HttpQuery\Unit\Cursor;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use TinyBlocks\HttpQuery\Cursor\Token;
use TinyBlocks\HttpQuery\Exceptions\CursorIsInvalid;
use TinyBlocks\HttpQuery\Internal\Cursor\CursorCodec;

final class TokenTest extends TestCase
{
    public function testFromKeysWhenKeysGivenThenIsPresent(): void
    {
        /** @Given a token backed by ordering key values */
        $token = Token::fromKeys(keys: ['x']);

        /** @When inspecting whether the token is absent */
        $isAbsent = $token->isAbsent();

        /** @Then it reports itself as present */
        self::assertFalse($isAbsent);
    }

    public function testFromWhenEmptyTokenGivenThenIsAbsent(): void
    {
        /** @Given a token backed by an empty incoming string */
        $token = Token::from(token: '');

        /** @When inspecting whether the token is absent */
        $isAbsent = $token->isAbsent();

        /** @Then it reports itself as absent */
        self::assertTrue($isAbsent);
    }

    public function testFromWhenNonEmptyTokenGivenThenIsPresent(): void
    {
        /** @Given a token backed by a non-empty incoming string */
        $token = Token::from(token: 'abc');

        /** @When inspecting whether the token is absent */
        $isAbsent = $token->isAbsent();

        /** @Then it reports itself as present */
        self::assertFalse($isAbsent);
    }

    public function testNoneThenAbsentWithEmptyKeysAndEmptyToken(): void
    {
        /** @Given an absent token */
        $token = Token::none();

        /** @When inspecting the absent token */
        $isAbsent = $token->isAbsent();

        /** @Then it reports itself as absent */
        self::assertTrue($isAbsent);

        /** @And it decodes to no key values */
        self::assertSame([], $token->toArray());

        /** @And it renders an empty string */
        self::assertSame('', $token->toString());
    }

    public function testFromKeysWhenKeysGivenThenDecodesToTheSameKeys(): void
    {
        /** @Given a token backed by ordering key values */
        $token = Token::fromKeys(keys: ['2024-01-01', 42]);

        /** @When decoding the token into key values */
        $keys = $token->toArray();

        /** @Then it yields the original key values */
        self::assertSame(['2024-01-01', 42], $keys);
    }

    public function testFromKeysWhenKeysAreGappedThenDecodesToAReindexedList(): void
    {
        /** @Given a token backed by ordering key values held under gapped integer keys */
        $token = Token::fromKeys(keys: [5 => 'x', 9 => 'y']);

        /** @When decoding the token into key values */
        $keys = $token->toArray();

        /** @Then it yields the values as a zero-based list */
        self::assertSame(['x', 'y'], $keys);
    }

    public function testKeyedByWhenTokenAbsentThenEveryFieldIsNull(): void
    {
        /** @Given an absent token */
        $token = Token::none();

        /** @When keying the decoded values by the sort field names */
        $keyed = $token->keyedBy(fields: ['created_at', 'id']);

        /** @Then every field is present with a null value */
        self::assertSame(['created_at' => null, 'id' => null], $keyed);
    }

    public function testKeyedByWhenKeysPresentThenValuesAreKeyedByField(): void
    {
        /** @Given a token backed by ordering key values */
        $token = Token::fromKeys(keys: ['2024-01-01', 42]);

        /** @When keying the decoded values by the sort field names */
        $keyed = $token->keyedBy(fields: ['created_at', 'id']);

        /** @Then the values are keyed by the field names in order */
        self::assertSame(['created_at' => '2024-01-01', 'id' => 42], $keyed);
    }

    public function testKeyedByWhenCountMismatchesThenThrowsCursorIsInvalid(): void
    {
        /** @Given a token backed by a single key value */
        $token = Token::fromKeys(keys: [5]);

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);

        /** @When keying the decoded values by two field names */
        $token->keyedBy(fields: ['created_at', 'id']);
    }

    public function testToArrayWhenTokenCarriesInvalidCharacterThenThrowsCursorIsInvalid(): void
    {
        /** @Given a token carrying a character outside the base64url alphabet */
        $token = Token::from(token: 'WzEsMl0!');

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage('could not be decoded');

        /** @When decoding the token into key values */
        $token->toArray();
    }

    public function testToArrayWhenTokenDecodesToScalarThenThrowsCursorIsInvalid(): void
    {
        /** @Given a base64url token whose decoded payload is the JSON scalar 5 */
        $token = Token::from(token: rtrim(strtr(base64_encode('5'), '+/', '-_'), '='));

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage('could not be decoded');

        /** @When decoding the token into key values */
        $token->toArray();
    }

    public function testToArrayWhenTokenDecodesToObjectThenThrowsCursorIsInvalid(): void
    {
        /** @Given a base64url token whose decoded payload is a JSON object rather than a list */
        $token = Token::from(token: rtrim(strtr(base64_encode('{"a":1}'), '+/', '-_'), '='));

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage('could not be decoded');

        /** @When decoding the token into key values */
        $token->toArray();
    }

    public function testToArrayWhenTokenDecodesToListWithNestedStructureThenThrowsCursorIsInvalid(): void
    {
        /** @Given a base64url token whose decoded list carries a nested array and object */
        $token = Token::from(token: rtrim(strtr(base64_encode('[["x"],{"a":1}]'), '+/', '-_'), '='));

        /** @Then an exception indicating the cursor token is invalid is raised */
        $this->expectException(CursorIsInvalid::class);
        $this->expectExceptionMessage('could not be decoded');

        /** @When decoding the token into key values */
        $token->toArray();
    }

    public function testFromKeysWhenRoundTrippedThroughTokenThenYieldsTheOriginalKeys(): void
    {
        /** @Given the opaque token produced from ordering key values */
        $token = Token::fromKeys(keys: ['2024-01-01', 42])->toString();

        /** @And the token is not empty */
        self::assertNotSame('', $token);

        /** @When rebuilding a token from that string and decoding it */
        $keys = Token::from(token: $token)->toArray();

        /** @Then it yields the original key values */
        self::assertSame(['2024-01-01', 42], $keys);
    }

    public function testFromKeysWhenEncodedThenTokenIsUrlSafeAndUnpadded(): void
    {
        /** @Given a token backed by key values whose base64 payload carries plus, slash, and padding */
        $token = Token::fromKeys(keys: ['>>>???']);

        /** @When rendering the opaque token */
        $rendered = $token->toString();

        /** @Then it renders the base64url form, translating plus and slash and dropping the padding */
        self::assertSame('WyI-Pj4_Pz8iXQ', $rendered);
    }

    public function testFromKeysWhenRoundTrippedThroughUrlSafeTokenThenYieldsTheOriginalKeys(): void
    {
        /** @Given the opaque token produced from key values whose base64 payload carries plus and slash */
        $token = Token::fromKeys(keys: ['>>>???'])->toString();

        /** @When rebuilding a token from that URL-safe string and decoding it */
        $keys = Token::from(token: $token)->toArray();

        /** @Then it yields the original key values */
        self::assertSame(['>>>???'], $keys);
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
