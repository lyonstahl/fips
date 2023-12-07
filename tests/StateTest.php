<?php

declare(strict_types=1);

namespace Tests;

use LyonStahl\Fips\Exception\StateException;
use LyonStahl\Fips\State;
use PHPUnit\Framework\TestCase;

class StateTest extends TestCase
{
    public function testConstructor()
    {
        $state = new State('Test State', 'TS', '01', 'US-TS', 'T2', 'T3');

        $this->assertEquals('Test State', $state->name);
        $this->assertEquals('TS', $state->abbreviation);
        $this->assertEquals('01', $state->fips);
        $this->assertEquals('US-TS', $state->iso);
        $this->assertEquals('T2', $state->usps);
        $this->assertEquals('T3', $state->uscg);
    }

    public function testAll()
    {
        $states = State::all();

        $this->assertIsArray($states);
        $this->assertNotEmpty($states);
        $this->assertContainsOnlyInstancesOf(State::class, $states);
    }

    public function testFindStateByName()
    {
        $state = State::fromName('California');

        $this->assertEquals('California', $state->name);
        $this->assertEquals('CA', $state->abbreviation);
        $this->assertEquals('06', $state->fips);
        $this->assertEquals('US-CA', $state->iso);
        $this->assertEquals('CA', $state->usps);
        $this->assertEquals('CF', $state->uscg);
    }

    public function testFindInvalidStateByName()
    {
        $this->expectException(StateException::class);
        State::fromName('Invalid');
    }

    public function testFindStateByFips()
    {
        $state = State::fromFips('06');

        $this->assertEquals('California', $state->name);
        $this->assertEquals('CA', $state->abbreviation);
        $this->assertEquals('06', $state->fips);
        $this->assertEquals('US-CA', $state->iso);
        $this->assertEquals('CA', $state->usps);
        $this->assertEquals('CF', $state->uscg);
    }

    public function testFindInvalidStateByFips()
    {
        $this->expectException(StateException::class);
        State::fromFips('99');
    }

    public function testFindStateByAbbr()
    {
        $state = State::fromAbbr('CA');

        $this->assertEquals('California', $state->name);
        $this->assertEquals('CA', $state->abbreviation);
        $this->assertEquals('06', $state->fips);
        $this->assertEquals('US-CA', $state->iso);
        $this->assertEquals('CA', $state->usps);
        $this->assertEquals('CF', $state->uscg);
    }

    public function testFindInvalidStateByAbbr()
    {
        $this->expectException(StateException::class);
        State::fromFips('XX');
    }

    public function testGetCounties()
    {
        $state = State::fromName('California');

        $this->assertCount(58, $state->getCounties());
    }
}
