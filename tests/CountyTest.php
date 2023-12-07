<?php

declare(strict_types=1);

namespace Tests;

use LyonStahl\Fips\County;
use LyonStahl\Fips\Exception\CountyException;
use LyonStahl\Fips\Exception\StateException;
use PHPUnit\Framework\TestCase;

class CountyTest extends TestCase
{
    public function testConstructor()
    {
        $county = new County('Test County', 'TC', '01', '06');

        $this->assertEquals('Test County', $county->name);
        $this->assertEquals('TC', $county->abbreviation);
        $this->assertEquals('01', $county->fips);

        $this->expectException(StateException::class);
        $county = new County('Test County', 'TC', '01', 'State');
    }

    public function testAll()
    {
        $counties = County::all();

        $this->assertIsArray($counties);
        $this->assertNotEmpty($counties);
        $this->assertContainsOnlyInstancesOf(County::class, $counties);
    }

    public function testFindCountyByName()
    {
        $county = County::fromName('Los Angeles');

        $this->assertEquals('Los Angeles', $county->name);
        // $this->assertEquals('LAS', $county->abbreviation);
        $this->assertEquals('037', $county->fips);
    }

    public function testFindInvalidCountyByName()
    {
        $this->expectException(CountyException::class);
        County::fromName('Invalid');
    }

    public function testFindCountyByFips()
    {
        $county = County::fromFips('06037');

        $this->assertEquals('Los Angeles', $county->name);
        // $this->assertEquals('LAS', $county->abbreviation);
        $this->assertEquals('037', $county->fips);
    }

    public function testFindInvalidCountyByFips()
    {
        $this->expectException(CountyException::class);
        County::fromFips('99999');
    }

    public function testFindCountyByAbbr()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testFindInvalidCountyByAbbr()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testGetState()
    {
        $county = County::fromName('Los Angeles');

        $this->assertEquals('California', $county->state->name);
        $this->assertEquals('CA', $county->state->abbreviation);
        $this->assertEquals('06', $county->state->fips);
        $this->assertEquals('US-CA', $county->state->iso);
        $this->assertEquals('CA', $county->state->usps);
        $this->assertEquals('CF', $county->state->uscg);
    }
}
