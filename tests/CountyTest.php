<?php

declare(strict_types=1);

namespace Tests;

use LogicException;
use LyonStahl\Fips\County;
use LyonStahl\Fips\Dataset;
use LyonStahl\Fips\Exception\AmbiguousMatchException;
use LyonStahl\Fips\Exception\InvalidIdentifierException;
use LyonStahl\Fips\Exception\NotFoundException;
use LyonStahl\Fips\State;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CountyTest extends TestCase
{
    public function testFipsLookupExposesExplicitCodeParts(): void
    {
        $county = County::fromFips('06037');

        self::assertSame('Los Angeles', $county->name);
        self::assertSame('Los Angeles County', $county->officialName);
        self::assertSame(County::TYPE_COUNTY, $county->type);
        self::assertSame('00277283', $county->ansiCode);
        self::assertSame('06037', $county->fips);
        self::assertSame('06', $county->stateFips);
        self::assertSame('037', $county->countyFips);
        self::assertSame('California', $county->state->name);
    }

    /**
     * @dataProvider countyTypeProvider
     */
    public function testCountyEquivalentTypes(string $fips, string $officialName, string $type): void
    {
        $county = County::fromFips($fips);

        self::assertSame($officialName, $county->officialName);
        self::assertSame($type, $county->type);
    }

    public static function countyTypeProvider(): array
    {
        return [
            'Alaska municipality' => ['02020', 'Anchorage Municipality', County::TYPE_MUNICIPALITY],
            'Louisiana parish' => ['22071', 'Orleans Parish', County::TYPE_PARISH],
            'Connecticut planning region' => ['09110', 'Capitol Planning Region', County::TYPE_PLANNING_REGION],
            'District of Columbia' => ['11001', 'District of Columbia', County::TYPE_DISTRICT],
            'Virginia independent city' => ['51510', 'Alexandria city', County::TYPE_INDEPENDENT_CITY],
            'Puerto Rico municipio' => ['72001', 'Adjuntas Municipio', County::TYPE_MUNICIPIO],
            'Nevada consolidated municipality' => ['32510', 'Carson City', County::TYPE_CONSOLIDATED_MUNICIPALITY],
        ];
    }

    public function testGlobalDuplicateNameThrowsWithCandidates(): void
    {
        try {
            County::fromName('Franklin');
            self::fail('Ambiguous county name was accepted.');
        } catch (AmbiguousMatchException $exception) {
            self::assertGreaterThan(10, count($exception->candidates()));
            self::assertContainsOnlyInstancesOf(County::class, $exception->candidates());
        }
    }

    public function testNameCanBeScopedByAnyStateIdentifier(): void
    {
        self::assertSame('51067', County::fromName('Franklin County', 'VA')->fips);
        self::assertSame('51067', County::fromName('Franklin County', '51')->fips);
        self::assertSame('51067', County::fromName('Franklin County', 'Virginia')->fips);
        self::assertSame('51067', County::fromName('Franklin County', State::fromAbbr('VA'))->fips);
    }

    public function testOfficialNamesResolveSameStateShortNameCollision(): void
    {
        self::assertCount(2, County::findByName('Baltimore', 'MD'));
        self::assertSame('24005', County::fromName('Baltimore County', 'MD')->fips);
        self::assertSame('24510', County::fromName('Baltimore city', 'MD')->fips);

        $this->expectException(AmbiguousMatchException::class);
        County::fromName('Baltimore', 'MD');
    }

    public function testFromAnySearchesNamesAndCodesWithoutPrecedence(): void
    {
        self::assertSame('06037', County::fromAny('06037')->fips);
        self::assertSame('51067', County::fromAny('067', 'VA')->fips);
        self::assertSame('19161', County::fromAny('Sac')->fips);
        self::assertSame('16001', County::fromAny('Ada')->fips);

        $this->expectException(AmbiguousMatchException::class);
        County::fromAny('Lee');
    }

    public function testUnicodeCaseWhitespaceAndOfficialSuffixesAreNormalized(): void
    {
        self::assertSame('35013', County::fromName('  DOÑA   ANA COUNTY  ')->fips);
        self::assertSame('72021', County::fromName('BAYAMÓN MUNICIPIO')->fips);
    }

    public function testNullableLookupsPreserveAmbiguity(): void
    {
        self::assertNull(County::tryFromFips('6037'));
        self::assertNull(County::tryFromFips('99999'));
        self::assertNull(County::tryFromName('Missing County'));

        $this->expectException(AmbiguousMatchException::class);
        County::tryFromName('Washington');
    }

    public function testMalformedAndUnknownFipsAreDistinct(): void
    {
        try {
            County::fromFips('6037');
            self::fail('Malformed FIPS code was accepted.');
        } catch (InvalidIdentifierException $exception) {
            self::assertStringContainsString('exactly 5 digits', $exception->getMessage());
        }

        $this->expectException(NotFoundException::class);
        County::fromFips('99999');
    }

    public function testInvalidStateScopeIsRejected(): void
    {
        $this->expectException(InvalidIdentifierException::class);
        County::fromName('Franklin', 51);
    }

    public function testAllRecordsRoundTripInFipsOrder(): void
    {
        $counties = County::all();

        self::assertCount(Dataset::countyCount(), $counties);
        self::assertSame('01001', $counties[0]->fips);
        self::assertSame('72153', $counties[count($counties) - 1]->fips);

        foreach ($counties as $county) {
            self::assertSame($county, County::fromFips($county->fips));
            self::assertSame($county->fips, $county->stateFips . $county->countyFips);
            self::assertSame($county->stateFips, $county->state->fips);
        }
    }

    public function testSerializationContainsNestedState(): void
    {
        $county = County::fromFips('06037');
        $data = $county->toArray();

        self::assertSame('06037', $data['fips']);
        self::assertSame('California', $data['state']['name']);
        self::assertSame($data, json_decode(json_encode($county), true));
    }

    public function testObjectsCannotBeConstructedOrMutatedByCallers(): void
    {
        $constructor = (new ReflectionClass(County::class))->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());

        $county = County::fromFips('06037');
        $this->expectException(LogicException::class);
        $county->name = 'Changed';
    }
}
