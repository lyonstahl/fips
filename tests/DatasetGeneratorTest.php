<?php

declare(strict_types=1);

namespace Tests;

use LyonStahl\Fips\Tools\DatasetGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class DatasetGeneratorTest extends TestCase
{
    public function testGeneratesValidatedDeterministicDataFromLocalArchives(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fips-generator-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));

        try {
            $stateArchive = $this->archive(
                $directory . DIRECTORY_SEPARATOR . 'states.zip',
                '2026_Gaz_state_national.txt',
                "USPS|GEOID|NAME\nPR|72|Puerto Rico\nAL|01|Alabama\n"
            );
            $countyArchive = $this->archive(
                $directory . DIRECTORY_SEPARATOR . 'counties.zip',
                '2026_Gaz_counties_national.txt',
                "USPS|GEOID|ANSICODE|NAME\nPR|72001|01804403|Adjuntas Municipio\nAL|01001|00161526|Autauga County\n"
            );

            $metadata = (new DatasetGenerator())->generate(
                '2026',
                $stateArchive,
                $countyArchive,
                $directory,
                '2026-09-18T00:00:00+00:00'
            );

            self::assertSame(2, $metadata['stateCount']);
            self::assertSame(2, $metadata['countyCount']);
            self::assertSame('01', json_decode(file_get_contents($directory . '/states.json'), true)[0]['fips']);
            $countyData = json_decode(file_get_contents($directory . '/counties.json'), true);
            self::assertSame('01001', $countyData[0]['fips']);
            self::assertSame(
                $metadata['dataChecksums']['states'],
                hash_file('sha256', $directory . '/states.json')
            );
        } finally {
            foreach (['states.zip', 'counties.zip', 'states.json', 'counties.json', 'metadata.json'] as $file) {
                $path = $directory . DIRECTORY_SEPARATOR . $file;
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            rmdir($directory);
        }
    }

    public function testValidationFailureDoesNotModifyExistingData(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fips-generator-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));

        try {
            foreach (['states.json', 'counties.json', 'metadata.json'] as $file) {
                file_put_contents($directory . DIRECTORY_SEPARATOR . $file, 'unchanged');
            }

            $stateArchive = $this->archive(
                $directory . DIRECTORY_SEPARATOR . 'states.zip',
                '2026_Gaz_state_national.txt',
                "USPS|GEOID|NAME\nAL|01|Alabama\n"
            );
            $countyArchive = $this->archive(
                $directory . DIRECTORY_SEPARATOR . 'counties.zip',
                '2026_Gaz_counties_national.txt',
                "USPS|GEOID|ANSICODE|NAME\nPR|01001|00161526|Autauga County\n"
            );

            try {
                (new DatasetGenerator())->generate('2026', $stateArchive, $countyArchive, $directory);
                self::fail('Mismatched state metadata was accepted.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('state abbreviation', $exception->getMessage());
            }

            foreach (['states.json', 'counties.json', 'metadata.json'] as $file) {
                self::assertSame('unchanged', file_get_contents($directory . DIRECTORY_SEPARATOR . $file));
            }
        } finally {
            foreach (['states.zip', 'counties.zip', 'states.json', 'counties.json', 'metadata.json'] as $file) {
                $path = $directory . DIRECTORY_SEPARATOR . $file;
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            rmdir($directory);
        }
    }

    public function testMalformedArchiveReportsTheArchiveError(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fips-generator-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unable to open a Census Gazetteer archive.');

            (new DatasetGenerator())->generate('2026', 'not a zip archive', 'not a zip archive', $directory);
        } finally {
            rmdir($directory);
        }
    }

    private function archive(string $path, string $entry, string $contents): string
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        self::assertTrue($zip->addFromString($entry, $contents));
        self::assertTrue($zip->close());

        return file_get_contents($path);
    }
}
