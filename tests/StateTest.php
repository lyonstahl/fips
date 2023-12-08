<?php

declare(strict_types=1);

namespace Tests;

use LyonStahl\Fips\Exception\StateException;
use LyonStahl\Fips\State;
use PHPUnit\Framework\TestCase;

class StateTest extends TestCase
{
    private static $expected = [
        'name' => 'California',
        'abbreviation' => 'CA',
        'fips' => '06',
        'iso' => 'US-CA',
        'usps' => 'CA',
        'uscg' => 'CF',
    ];

    public function testConstructor()
    {
        $expected = [
            'name' => 'Test State',
            'abbreviation' => 'TS',
            'fips' => '01',
            'iso' => 'US-TS',
            'usps' => 'T2',
            'uscg' => 'T3',
        ];

        $state = new State(
            $expected['name'],
            $expected['abbreviation'],
            $expected['fips'],
            $expected['iso'],
            $expected['usps'],
            $expected['uscg']
        );

        static::assertStateValid($state, $expected);
    }

    public function testAll()
    {
        $states = State::all();

        static::assertIsArray($states);
        static::assertNotEmpty($states);
        static::assertContainsOnlyInstancesOf(State::class, $states);
    }

    public function testFindStateByAny()
    {
        $state1 = State::fromAny(static::$expected['fips']);
        $state2 = State::fromAny(static::$expected['name']);
        $state3 = State::fromAny(static::$expected['abbreviation']);

        static::assertStateValid($state1);
        static::assertStateValid($state2);
        static::assertStateValid($state3);
    }

    public function testFindStateByInvalidAny()
    {
        static::expectException(StateException::class);
        static::expectExceptionCode(4);

        State::fromAny('Invalid');
    }

    public function testFindStateByName()
    {
        $state = State::fromName(static::$expected['name']);

        static::assertStateValid($state);
    }

    public function testFindStateByInvalidName()
    {
        static::expectException(StateException::class);
        static::expectExceptionCode(3);

        State::fromName('Invalid');
    }

    public function testFindStateByFips()
    {
        $state = State::fromFips(static::$expected['fips']);

        static::assertStateValid($state);
    }

    public function testFindStateByInvalidFips()
    {
        static::expectException(StateException::class);
        static::expectExceptionCode(1);

        State::fromFips('99');
    }

    public function testFindStateByAbbr()
    {
        $state = State::fromAbbr(static::$expected['abbreviation']);

        static::assertStateValid($state);
    }

    public function testFindStateByInvalidAbbr()
    {
        static::expectException(StateException::class);
        static::expectExceptionCode(2);

        State::fromAbbr('XX');
    }

    public function testGetCounties()
    {
        $state = State::fromName(static::$expected['name']);

        static::assertCount(58, $state->getCounties());
    }

    /**
     * Assert that the state is valid.
     */
    public static function assertStateValid(State $state, array $expected = null)
    {
        $expected = $expected ?? static::$expected;

        static::assertEquals($expected['name'], $state->name);
        static::assertEquals($expected['abbreviation'], $state->abbreviation);
        static::assertEquals($expected['fips'], $state->fips);
        static::assertEquals($expected['iso'], $state->iso);
        static::assertEquals($expected['usps'], $state->usps);
        static::assertEquals($expected['uscg'], $state->uscg);
    }
}
