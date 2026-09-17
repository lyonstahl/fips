<?php

declare(strict_types=1);

namespace Tests;

use LyonStahl\Fips\County;
use LyonStahl\Fips\Exception\CountyException;
use LyonStahl\Fips\Exception\StateException;
use PHPUnit\Framework\TestCase;

class CountyTest extends TestCase
{
    private static $expected = [
        'name' => 'Los Angeles',
        'abbreviation' => 'LA',
        'fips' => '037',
        'state' => [
            'name' => 'California',
            'abbreviation' => 'CA',
            'fips' => '06',
            'iso' => 'US-CA',
            'usps' => 'CA',
            'uscg' => 'CF',
        ],
        'fips5' => '06037',
    ];

    public function testConstructor()
    {
        $county = new County('Test County', 'TC', '01', '06');

        static::assertEquals('Test County', $county->name);
        static::assertEquals('TC', $county->abbreviation);
        static::assertEquals('01', $county->fips);

        static::expectException(StateException::class);
        $county = new County('Test County', 'TC', '01', 'State');
    }

    public function testAll()
    {
        $counties = County::all();

        static::assertIsArray($counties);
        static::assertNotEmpty($counties);
        static::assertContainsOnlyInstancesOf(County::class, $counties);
    }

    public function testFindCountyByAny()
    {
        $county1 = County::fromAny(static::$expected['fips5']);
        $county2 = County::fromAny(static::$expected['abbreviation']);
        $county3 = County::fromAny(static::$expected['name']);

        static::assertCountyValid($county1);
        static::assertCountyValid($county2);
        static::assertCountyValid($county3);
    }

    public function testFindCountyByInvalidAny()
    {
        static::expectException(CountyException::class);
        static::expectExceptionCode(4);

        County::fromAny('Invalid');
    }

    public function testFindCountyByName()
    {
        $county = County::fromName(static::$expected['name']);

        static::assertCountyValid($county);
    }

    public function testFindCountyByInvalidName()
    {
        static::expectException(CountyException::class);
        static::expectExceptionCode(3);

        County::fromName('Invalid');
    }

    public function testFindCountyByFips()
    {
        $county = County::fromFips(static::$expected['fips5']);

        static::assertCountyValid($county);
    }

    /**
     * @dataProvider countyFipsProvider
     */
    public function testFindCountyByFipsAcrossStates(string $fips, string $name, string $stateFips)
    {
        $county = County::fromFips($fips);

        static::assertSame($name, $county->name);
        static::assertSame($stateFips, $county->state->fips);
    }

    public static function countyFipsProvider(): array
    {
        return [
            'Alaska municipality' => ['02020', 'Anchorage', '02'],
            'Connecticut planning region' => ['09110', 'Capitol', '09'],
            'District of Columbia' => ['11001', 'District of Columbia', '11'],
            'Louisiana parish' => ['22071', 'Orleans', '22'],
            'Virginia independent city' => ['51510', 'Alexandria', '51'],
            'West Virginia county' => ['54031', 'Hardy', '54'],
        ];
    }

    public function testFindCountyByFipsForEveryState()
    {
        foreach (County::read() as $state => $counties) {
            $state = str_pad((string) $state, 2, '0', STR_PAD_LEFT);
            $county = County::fromFips($state.$counties[0]['fips']);

            static::assertSame($state, $county->state->fips);
            static::assertSame($counties[0]['name'], $county->name);
        }
    }

    public function testCountyDataIsValid()
    {
        $seen = [];

        foreach (County::read() as $state => $counties) {
            $state = str_pad((string) $state, 2, '0', STR_PAD_LEFT);
            foreach ($counties as $county) {
                $fips = $state.$county['fips'];

                static::assertMatchesRegularExpression('/^\d{5}$/', $fips);
                static::assertNotSame('', trim($county['name']));
                static::assertArrayNotHasKey($fips, $seen);
                $seen[$fips] = true;
            }
        }
    }

    public function testRetiredCountyFipsIsInvalid()
    {
        static::expectException(CountyException::class);

        County::fromFips('09001');
    }

    public function testFindCountyByInvalidFips()
    {
        static::expectException(CountyException::class);
        static::expectExceptionCode(1);

        County::fromFips('99999');
    }

    public function testFindCountyByAbbr()
    {
        $county = County::fromAbbr(static::$expected['abbreviation']);

        static::assertCountyValid($county);
    }

    public function testFindCountyByInvalidAbbr()
    {
        static::expectException(CountyException::class);
        static::expectExceptionCode(2);

        County::fromAbbr('XX');
    }

    /**
     * Assert that the county is valid.
     */
    public static function assertCountyValid(County $county, ?array $expected = null)
    {
        $expected = $expected ?? static::$expected;

        static::assertEquals($expected['name'], $county->name);
        static::assertEquals($expected['abbreviation'], $county->abbreviation);
        static::assertEquals($expected['fips'], $county->fips);

        StateTest::assertStateValid($county->state, $expected['state']);
    }
}
