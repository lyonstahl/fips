<?php

declare(strict_types=1);

namespace LyonStahl\Fips\Tools;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;

final class DatasetGenerator
{
    public static function stateUrl(string $year): string
    {
        return sprintf(
            'https://www2.census.gov/geo/docs/maps-data/data/gazetteer/%1$s_Gazetteer/%1$s_Gaz_state_national.zip',
            $year
        );
    }

    public static function countyUrl(string $year): string
    {
        return sprintf(
            'https://www2.census.gov/geo/docs/maps-data/data/gazetteer/%1$s_Gazetteer/%1$s_Gaz_counties_national.zip',
            $year
        );
    }

    /** @return array<string,mixed> */
    public function generate(
        string $year,
        string $stateArchive,
        string $countyArchive,
        string $targetDirectory,
        ?string $generatedAt = null
    ): array {
        if (!preg_match('/^\d{4}$/D', $year)) {
            throw new RuntimeException('The Census vintage must contain four digits.');
        }

        $parser = new GazetteerParser();
        $archive = new GazetteerArchive();
        $states = $parser->parseStates(
            $archive->extract($stateArchive, $year . '_Gaz_state_national.txt')
        );
        $counties = $parser->parseCounties(
            $archive->extract($countyArchive, $year . '_Gaz_counties_national.txt')
        );
        $this->validateRelationships($states, $counties);
        $counties = array_map(function (array $county): array {
            unset($county['stateUsps']);

            return $county;
        }, $counties);

        $statesJson = $this->encode($states, true);
        $countiesJson = $this->encode($counties, false);
        $metadata = [
            'vintage' => $year,
            'generatedAt' => $generatedAt ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            'sources' => [
                'states' => self::stateUrl($year),
                'counties' => self::countyUrl($year),
            ],
            'sourceChecksums' => [
                'states' => hash('sha256', $stateArchive),
                'counties' => hash('sha256', $countyArchive),
            ],
            'dataChecksums' => [
                'states' => hash('sha256', $statesJson),
                'counties' => hash('sha256', $countiesJson),
            ],
            'stateCount' => count($states),
            'countyCount' => count($counties),
        ];
        $metadataJson = $this->encode($metadata, true);

        if (!is_dir($targetDirectory)) {
            throw new RuntimeException(sprintf('Target data directory does not exist: %s', $targetDirectory));
        }

        $this->atomicWrite($targetDirectory . '/states.json', $statesJson);
        $this->atomicWrite($targetDirectory . '/counties.json', $countiesJson);
        $this->atomicWrite($targetDirectory . '/metadata.json', $metadataJson);

        return $metadata;
    }

    /**
     * @param array<int,array<string,string>> $states
     * @param array<int,array<string,string>> $counties
     */
    private function validateRelationships(array $states, array $counties): void
    {
        $stateCounts = [];
        $stateUsps = [];
        foreach ($states as $state) {
            $stateCounts[$state['fips']] = 0;
            $stateUsps[$state['fips']] = $state['usps'];
        }

        foreach ($counties as $county) {
            if (!array_key_exists($county['stateFips'], $stateCounts)) {
                throw new RuntimeException(sprintf(
                    'County %s refers to missing state %s.',
                    $county['officialName'],
                    $county['stateFips']
                ));
            }

            if ($county['stateUsps'] !== $stateUsps[$county['stateFips']]) {
                throw new RuntimeException(sprintf(
                    'County %s has state abbreviation %s instead of %s.',
                    $county['officialName'],
                    $county['stateUsps'],
                    $stateUsps[$county['stateFips']]
                ));
            }
            ++$stateCounts[$county['stateFips']];
        }

        foreach ($stateCounts as $stateFips => $countyCount) {
            if ($countyCount === 0) {
                throw new RuntimeException(sprintf('State %s contains no county records.', $stateFips));
            }
        }
    }

    /** @param mixed $value */
    private function encode($value, bool $pretty): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return json_encode($value, $flags) . "\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode generated Census data.', 0, $exception);
        }
    }

    private function atomicWrite(string $target, string $contents): void
    {
        $temporary = tempnam(dirname($target), '.fips-');
        if ($temporary === false) {
            throw new RuntimeException(sprintf('Unable to create a temporary file for %s.', $target));
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $target)) {
                throw new RuntimeException(sprintf('Unable to write generated data to %s.', $target));
            }
        } finally {
            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
