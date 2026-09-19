<?php

declare(strict_types=1);

namespace Tests;

use LogicException;
use LyonStahl\Fips\County;
use LyonStahl\Fips\Dataset;
use LyonStahl\Fips\Exception\InvalidIdentifierException;
use LyonStahl\Fips\Exception\NotFoundException;
use LyonStahl\Fips\State;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class StateTest extends TestCase
{
    public function testLookupByEveryIdentifier(): void
    {
        $byFips = State::fromFips('06');
        $byUsps = State::fromUsps('ca');
        $byAbbreviation = State::fromAbbr('CA');
        $byName = State::fromName('  CALIFORNIA ');

        foreach ([$byFips, $byUsps, $byAbbreviation, $byName] as $state) {
            self::assertSame('California', $state->name);
            self::assertSame('06', $state->fips);
            self::assertSame('CA', $state->usps);
            self::assertSame('CA', $state->abbreviation);
        }
    }

    public function testFromAnyUsesEveryApplicableIdentifier(): void
    {
        self::assertSame('California', State::fromAny('06')->name);
        self::assertSame('California', State::fromAny('ca')->name);
        self::assertSame('California', State::fromAny('California')->name);
    }

    public function testAllIncludesPuertoRicoInFipsOrder(): void
    {
        $states = State::all();

        self::assertCount(Dataset::stateCount(), $states);
        self::assertSame('01', $states[0]->fips);
        self::assertSame('72', $states[count($states) - 1]->fips);
        self::assertSame('Puerto Rico', State::fromUsps('PR')->name);

        foreach ($states as $state) {
            self::assertSame($state, State::fromFips($state->fips));
            self::assertSame($state, State::fromUsps($state->usps));
            self::assertSame($state, State::fromName($state->name));
        }
    }

    public function testInvalidAndUnknownIdentifiersAreDistinct(): void
    {
        try {
            State::fromFips('6');
            self::fail('Malformed FIPS code was accepted.');
        } catch (InvalidIdentifierException $exception) {
            self::assertStringContainsString('exactly 2 digits', $exception->getMessage());
        }

        $this->expectException(NotFoundException::class);
        State::fromFips('99');
    }

    public function testNullableLookupsReturnNullForInvalidOrMissingValues(): void
    {
        self::assertNull(State::tryFromFips('6'));
        self::assertNull(State::tryFromFips('99'));
        self::assertNull(State::tryFromName('Nowhere'));
        self::assertNull(State::tryFromAny(''));
    }

    public function testStateProvidesScopedCountyLookups(): void
    {
        $virginia = State::fromAbbr('VA');

        self::assertSame('Fairfax County', $virginia->countyFromFips('059')->officialName);
        self::assertSame('Fairfax County', $virginia->countyFromName('Fairfax County')->officialName);
        self::assertCount(2, $virginia->findCounties('Fairfax'));
        self::assertContainsOnlyInstancesOf(County::class, $virginia->counties());
    }

    public function testStateSerializesWithStableAliases(): void
    {
        $state = State::fromFips('06');
        $expected = [
            'name' => 'California',
            'fips' => '06',
            'usps' => 'CA',
            'abbreviation' => 'CA',
        ];

        self::assertSame($expected, $state->toArray());
        self::assertSame($expected, json_decode(json_encode($state), true));
    }

    public function testObjectsCannotBeConstructedOrMutatedByCallers(): void
    {
        $constructor = (new ReflectionClass(State::class))->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());

        $state = State::fromFips('06');
        $this->expectException(LogicException::class);
        $state->name = 'Changed';
    }

    public function testObjectsCannotBeUnset(): void
    {
        $state = State::fromFips('06');
        $this->expectException(LogicException::class);
        unset($state->name);
    }

    public function testUnknownPropertiesFailClearly(): void
    {
        $state = State::fromFips('06');
        self::assertFalse(isset($state->iso));

        $this->expectException(LogicException::class);
        $state->iso;
    }
}
