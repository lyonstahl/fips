<?php

declare(strict_types=1);

namespace LyonStahl\Fips;

use JsonException;
use LyonStahl\Fips\Exception\DataException;

final class Dataset
{
    private const SOURCE = __DIR__ . '/../data/metadata.json';

    /** @var array<string,mixed>|null */
    private static $metadata;

    private function __construct()
    {
    }

    public static function vintage(): string
    {
        return self::metadata()['vintage'];
    }

    public static function generatedAt(): string
    {
        return self::metadata()['generatedAt'];
    }

    /** @return array<string,string> */
    public static function sources(): array
    {
        return self::metadata()['sources'];
    }

    /** @return array<string,string> */
    public static function sourceChecksums(): array
    {
        return self::metadata()['sourceChecksums'];
    }

    /** @return array<string,string> */
    public static function dataChecksums(): array
    {
        return self::metadata()['dataChecksums'];
    }

    public static function stateCount(): int
    {
        return self::metadata()['stateCount'];
    }

    public static function countyCount(): int
    {
        return self::metadata()['countyCount'];
    }

    /** @return array<string,mixed> */
    public static function metadata(): array
    {
        if (self::$metadata !== null) {
            return self::$metadata;
        }

        $contents = @file_get_contents(self::SOURCE);
        if ($contents === false) {
            throw new DataException('Unable to read packaged dataset metadata.');
        }

        try {
            $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DataException('Packaged dataset metadata is invalid JSON.', 0, $exception);
        }

        self::$metadata = $metadata;

        return self::$metadata;
    }

    /**
     * @internal
     *
     * @return array<int,array<string,string>>
     */
    public static function records(string $name): array
    {
        if (!in_array($name, ['states', 'counties'], true)) {
            throw new DataException(sprintf('Unknown packaged dataset: %s', $name));
        }

        $contents = @file_get_contents(__DIR__ . '/../data/' . $name . '.json');
        if ($contents === false) {
            throw new DataException(sprintf('Unable to read packaged %s data.', $name));
        }

        try {
            $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DataException(sprintf('Packaged %s data is invalid JSON.', $name), 0, $exception);
        }

        if (!is_array($records)) {
            throw new DataException(sprintf('Packaged %s data must be a JSON array.', $name));
        }

        return $records;
    }
}
