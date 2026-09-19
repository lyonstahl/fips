<?php

declare(strict_types=1);

namespace Tests;

use LyonStahl\Fips\Dataset;
use LyonStahl\Fips\Exception\DataException;
use PHPUnit\Framework\TestCase;

final class DatasetTest extends TestCase
{
    public function testMetadataDescribesPackagedData(): void
    {
        $vintage = Dataset::vintage();

        self::assertMatchesRegularExpression('/^\d{4}$/D', $vintage);
        self::assertSame(count(Dataset::records('states')), Dataset::stateCount());
        self::assertSame(count(Dataset::records('counties')), Dataset::countyCount());
        self::assertStringContainsString($vintage . '_Gaz_state_national.zip', Dataset::sources()['states']);
        self::assertStringContainsString($vintage . '_Gaz_counties_national.zip', Dataset::sources()['counties']);
        self::assertNotFalse(strtotime(Dataset::generatedAt()));
    }

    public function testPackagedChecksumsMatchMetadata(): void
    {
        $checksums = Dataset::dataChecksums();

        self::assertSame($checksums['states'], hash_file('sha256', __DIR__ . '/../data/states.json'));
        self::assertSame($checksums['counties'], hash_file('sha256', __DIR__ . '/../data/counties.json'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', Dataset::sourceChecksums()['states']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', Dataset::sourceChecksums()['counties']);
    }

    public function testUnknownPackagedDatasetIsRejected(): void
    {
        $this->expectException(DataException::class);
        Dataset::records('../composer');
    }
}
