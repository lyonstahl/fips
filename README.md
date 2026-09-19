# Census FIPS lookups for PHP

`lyonstahl/fips` provides fast, offline lookups for U.S. state and county FIPS codes using Census Gazetteer data.

## Install

```shell
composer require lyonstahl/fips
```

Version 2 requires PHP 7.3+ with `ctype`, `json`, and `mbstring`.

## Data versions

| Package version | Year | Dataset |
| --- | ---: | --- |
| 2.2.0 | 2026 | Census Gazetteer |
| 2.1.0 | 2025 | Census Gazetteer |
| 2.0.0 | 2024 | Census Gazetteer |
| 1.1.1 | 2023 | Legacy package data; not Census-backed |

## States

```php
use LyonStahl\Fips\State;

$state = State::fromName('California');

$state->name;         // California
$state->fips;         // 06
$state->usps;         // CA
$state->abbreviation; // CA
```

Look up a state with `fromFips()`, `fromUsps()`, `fromAbbr()`, `fromName()`, or `fromAny()`. `State::all()` returns every state-level record in FIPS order.

## Counties

```php
use LyonStahl\Fips\County;

$county = County::fromFips('06037');

$county->name;         // Los Angeles
$county->officialName; // Los Angeles County
$county->fips;         // 06037
$county->stateFips;    // 06
$county->countyFips;   // 037
$county->state->name;  // California
```

County `fips` is always the complete five-digit identifier. `stateFips` and `countyFips` expose its two parts.

County-equivalent types such as parishes, boroughs, municipios, independent cities, and planning regions are available through `$county->type` and the `County::TYPE_*` constants.

## Names and ambiguity

Repeated county names require a state or a multi-result lookup:

```php
County::fromName('Los Angeles');
County::fromName('Franklin County', 'VA');
County::findByName('Franklin');

$virginia = State::fromAbbr('VA');
$virginia->countyFromName('Arlington');
$virginia->countyFromFips('059');
$virginia->counties();
```

An unscoped singular lookup throws `AmbiguousMatchException` when several records match. Its `candidates()` method returns those records.

Each singular lookup also has a nullable `tryFrom...()` form. Invalid or missing values return `null`; ambiguous names still throw.

## Objects and metadata

State and county objects are immutable, implement `JsonSerializable`, and provide `toArray()`.

```php
use LyonStahl\Fips\Dataset;

Dataset::vintage();
Dataset::generatedAt();
Dataset::sources();
Dataset::sourceChecksums();
Dataset::dataChecksums();
Dataset::stateCount();
Dataset::countyCount();
```

Lookups never access the network. The bundled scope follows the national Census State and County Gazetteer files, which currently cover the 50 states, the District of Columbia, and Puerto Rico.

## Development

```shell
composer install
composer check
```

Run `composer test`, `composer style`, `composer style:fix`, or `composer analyse` for individual checks.

To refresh the data locally:

```shell
php tools/generate-data 2024
```

The generator validates both national Gazetteer archives before replacing the packaged files. It also records their URLs, checksums, vintage, and record counts.

The **Refresh Census data and release** GitHub workflow checks monthly for the next Census vintage. Unavailable or unchanged archives produce a no-op. A real change is generated and tested before the workflow updates this table, commits, tags, and publishes a release. Maintainers can also run it manually with a year and a new `MAJOR.MINOR.PATCH` version.

Data comes from the official [U.S. Census Gazetteer Files](https://www.census.gov/geographies/reference-files/time-series/geo/gazetteer-files.html).
