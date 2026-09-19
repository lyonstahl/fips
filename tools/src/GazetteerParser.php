<?php

declare(strict_types=1);

namespace LyonStahl\Fips\Tools;

use RuntimeException;

final class GazetteerParser
{
    /** @return array<int,array<string,string>> */
    public function parseStates(string $contents): array
    {
        $rows = $this->rows($contents, ['USPS', 'GEOID', 'NAME']);
        $states = [];
        $seen = [];

        foreach ($rows as $row) {
            $fips = $row['GEOID'];
            $usps = $row['USPS'];
            $name = trim($row['NAME']);

            if (
                !preg_match('/^\d{2}$/D', $fips)
                || !preg_match('/^[A-Z]{2}$/D', $usps)
                || $name === ''
                || isset($seen[$fips])
            ) {
                throw new RuntimeException(sprintf('Invalid or duplicate state row: %s', $fips));
            }

            $seen[$fips] = true;
            $states[] = ['name' => $name, 'fips' => $fips, 'usps' => $usps];
        }

        usort($states, function (array $left, array $right): int {
            return strcmp($left['fips'], $right['fips']);
        });

        return $states;
    }

    /** @return array<int,array<string,string>> */
    public function parseCounties(string $contents): array
    {
        $rows = $this->rows($contents, ['USPS', 'GEOID', 'ANSICODE', 'NAME']);
        $counties = [];
        $seen = [];

        foreach ($rows as $row) {
            $fips = $row['GEOID'];
            $ansiCode = $row['ANSICODE'];
            $stateUsps = $row['USPS'];
            $officialName = trim($row['NAME']);

            if (
                !preg_match('/^\d{5}$/D', $fips)
                || !preg_match('/^\d{8}$/D', $ansiCode)
                || !preg_match('/^[A-Z]{2}$/D', $stateUsps)
                || $officialName === ''
                || isset($seen[$fips])
            ) {
                throw new RuntimeException(sprintf('Invalid or duplicate county row: %s', $fips));
            }

            [$name, $type] = $this->countyNameAndType($officialName);
            $seen[$fips] = true;
            $counties[] = [
                'name' => $name,
                'officialName' => $officialName,
                'type' => $type,
                'ansiCode' => $ansiCode,
                'fips' => $fips,
                'stateFips' => substr($fips, 0, 2),
                'countyFips' => substr($fips, 2, 3),
                'stateUsps' => $stateUsps,
            ];
        }

        usort($counties, function (array $left, array $right): int {
            return strcmp($left['fips'], $right['fips']);
        });

        return $counties;
    }

    /**
     * @param string[] $requiredColumns
     *
     * @return array<int,array<string,string>>
     */
    private function rows(string $contents, array $requiredColumns): array
    {
        $firstLine = strstr($contents, "\n", true);
        $delimiter = strpos($firstLine === false ? $contents : $firstLine, "\t") === false ? '|' : "\t";
        $stream = fopen('php://temp', 'r+');
        if ($stream === false || fwrite($stream, $contents) === false) {
            throw new RuntimeException('Unable to open Gazetteer contents.');
        }
        rewind($stream);

        $header = fgetcsv($stream, 0, $delimiter, '"', '\\');
        if (!is_array($header)) {
            fclose($stream);
            throw new RuntimeException('The Gazetteer file is empty.');
        }

        $header = array_map(function ($value): string {
            return is_string($value) ? $value : '';
        }, $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? '';
        $columns = array_flip($header);
        foreach ($requiredColumns as $requiredColumn) {
            if (!isset($columns[$requiredColumn])) {
                fclose($stream);
                throw new RuntimeException(sprintf('The Gazetteer file is missing %s.', $requiredColumn));
            }
        }

        $rows = [];
        while (($values = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }

            $row = [];
            foreach ($requiredColumns as $column) {
                $row[$column] = isset($values[$columns[$column]]) ? trim($values[$columns[$column]]) : '';
            }
            $rows[] = $row;
        }
        fclose($stream);

        if ($rows === []) {
            throw new RuntimeException('The Gazetteer file contains no data rows.');
        }

        return $rows;
    }

    /** @return string[] */
    private function countyNameAndType(string $officialName): array
    {
        if ($officialName === 'District of Columbia') {
            return [$officialName, 'district'];
        }

        if ($officialName === 'Carson City') {
            return [$officialName, 'consolidated_municipality'];
        }

        $types = [
            'City and Borough' => 'city_and_borough',
            'Planning Region' => 'planning_region',
            'Census Area' => 'census_area',
            'Municipality' => 'municipality',
            'Municipio' => 'municipio',
            'County' => 'county',
            'Parish' => 'parish',
            'Borough' => 'borough',
            'city' => 'independent_city',
        ];

        foreach ($types as $suffix => $type) {
            $ending = ' ' . $suffix;
            if (substr($officialName, -strlen($ending)) === $ending) {
                $name = substr($officialName, 0, -strlen($ending));
                if ($name === '') {
                    break;
                }

                return [$name, $type];
            }
        }

        throw new RuntimeException(sprintf('Unrecognized county-equivalent name: %s', $officialName));
    }
}
