<?php

declare(strict_types=1);

namespace Tests;

use LyonStahl\Fips\Tools\GazetteerParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GazetteerParserTest extends TestCase
{
    public function testParsesStateRowsAndSortsByFips(): void
    {
        $contents = "USPS\tGEOID\tNAME\nPR\t72\tPuerto Rico\nAL\t01\tAlabama\n";
        $states = (new GazetteerParser())->parseStates($contents);

        self::assertSame('01', $states[0]['fips']);
        self::assertSame('72', $states[1]['fips']);
    }

    /**
     * @dataProvider countyNameProvider
     */
    public function testParsesOfficialCountyNames(string $officialName, string $name, string $type): void
    {
        $contents = "USPS|GEOID|ANSICODE|NAME\nXX|01001|00161526|{$officialName}\n";
        $county = (new GazetteerParser())->parseCounties($contents)[0];

        self::assertSame($name, $county['name']);
        self::assertSame($officialName, $county['officialName']);
        self::assertSame($type, $county['type']);
    }

    public static function countyNameProvider(): array
    {
        return [
            ['Autauga County', 'Autauga', 'county'],
            ['Orleans Parish', 'Orleans', 'parish'],
            ['Adjuntas Municipio', 'Adjuntas', 'municipio'],
            ['Alexandria city', 'Alexandria', 'independent_city'],
            ['Carson City', 'Carson City', 'consolidated_municipality'],
            ['District of Columbia', 'District of Columbia', 'district'],
        ];
    }

    public function testRejectsUnknownCountyEquivalentSuffix(): void
    {
        $contents = "USPS|GEOID|ANSICODE|NAME\nXX|01001|00161526|Unknown Division\n";

        $this->expectException(RuntimeException::class);
        (new GazetteerParser())->parseCounties($contents);
    }

    public function testRejectsMissingColumns(): void
    {
        $this->expectException(RuntimeException::class);
        (new GazetteerParser())->parseStates("GEOID|NAME\n01|Alabama\n");
    }
}
