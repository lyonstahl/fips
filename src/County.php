<?php

declare(strict_types=1);

namespace LyonStahl\Fips;

use JsonSerializable;
use LogicException;
use LyonStahl\Fips\Exception\AmbiguousMatchException;
use LyonStahl\Fips\Exception\InvalidIdentifierException;
use LyonStahl\Fips\Exception\NotFoundException;
use LyonStahl\Fips\Internal\Normalizer;

/**
 * An immutable county or county-equivalent record from the Census Gazetteer.
 *
 * @property-read string $name Short name without the legal/statistical suffix
 * @property-read string $officialName
 * @property-read string $type
 * @property-read string $ansiCode
 * @property-read string $fips
 * @property-read string $stateFips
 * @property-read string $countyFips
 * @property-read State  $state
 */
final class County implements JsonSerializable
{
    public const TYPE_BOROUGH = 'borough';
    public const TYPE_CENSUS_AREA = 'census_area';
    public const TYPE_CITY_AND_BOROUGH = 'city_and_borough';
    public const TYPE_CONSOLIDATED_MUNICIPALITY = 'consolidated_municipality';
    public const TYPE_COUNTY = 'county';
    public const TYPE_DISTRICT = 'district';
    public const TYPE_INDEPENDENT_CITY = 'independent_city';
    public const TYPE_MUNICIPALITY = 'municipality';
    public const TYPE_MUNICIPIO = 'municipio';
    public const TYPE_PARISH = 'parish';
    public const TYPE_PLANNING_REGION = 'planning_region';

    /** @var string */
    private $name;

    /** @var string */
    private $officialName;

    /** @var string */
    private $type;

    /** @var string */
    private $ansiCode;

    /** @var string */
    private $fips;

    /** @var string */
    private $stateFips;

    /** @var string */
    private $countyFips;

    /** @var State */
    private $state;

    /** @var array<string,self>|null */
    private static $byFips;

    /** @var array<string,array<string,self>>|null */
    private static $byName;

    /** @var array<string,array<string,self>>|null */
    private static $byState;

    private function __construct(
        string $name,
        string $officialName,
        string $type,
        string $ansiCode,
        string $fips,
        string $stateFips,
        string $countyFips,
        State $state
    ) {
        $this->name = $name;
        $this->officialName = $officialName;
        $this->type = $type;
        $this->ansiCode = $ansiCode;
        $this->fips = $fips;
        $this->stateFips = $stateFips;
        $this->countyFips = $countyFips;
        $this->state = $state;
    }

    /** @return self[] */
    public static function all(): array
    {
        self::initialize();

        return array_values(self::$byFips ?? []);
    }

    public static function fromFips(string $fips): self
    {
        $fips = Normalizer::digits($fips, 5, 'County FIPS code');
        self::initialize();

        if (!isset(self::$byFips[$fips])) {
            throw new NotFoundException(sprintf('No county found with FIPS code: %s', $fips));
        }

        return self::$byFips[$fips];
    }

    public static function tryFromFips(string $fips): ?self
    {
        return self::tryLookup(function () use ($fips): self {
            return self::fromFips($fips);
        });
    }

    /** @param State|string|null $state */
    public static function fromName(string $name, $state = null): self
    {
        $scope = $state === null ? null : self::resolveState($state);
        $matches = self::findNameMatches(Normalizer::name($name), $scope);
        $scopeDescription = $scope === null ? '' : ' in ' . $scope->name;

        return self::one($matches, sprintf('county name %s%s', $name, $scopeDescription));
    }

    /** @param State|string|null $state */
    public static function tryFromName(string $name, $state = null): ?self
    {
        return self::tryLookup(function () use ($name, $state): self {
            return self::fromName($name, $state);
        });
    }

    /**
     * @param State|string|null $state
     *
     * @return self[]
     */
    public static function findByName(string $name, $state = null): array
    {
        $key = Normalizer::name($name);
        $scope = $state === null ? null : self::resolveState($state);

        return self::findNameMatches($key, $scope);
    }

    /** @param State|string|null $state */
    public static function fromAny(string $value, $state = null): self
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidIdentifierException('A county identifier must not be empty.');
        }

        $scope = $state === null ? null : self::resolveState($state);
        self::initialize();
        $matches = [];

        if (preg_match('/^\d{5}$/D', $value) && isset(self::$byFips[$value])) {
            $county = self::$byFips[$value];
            if ($scope === null || $county->stateFips === $scope->fips) {
                $matches[$county->fips] = $county;
            }
        }

        if ($scope !== null && preg_match('/^\d{3}$/D', $value)) {
            $fips = $scope->fips . $value;
            if (isset(self::$byFips[$fips])) {
                $matches[$fips] = self::$byFips[$fips];
            }
        }

        $name = Normalizer::name($value);
        foreach (self::$byName[$name] ?? [] as $fips => $county) {
            if ($scope === null || $county->stateFips === $scope->fips) {
                $matches[$fips] = $county;
            }
        }

        $scopeDescription = $scope === null ? '' : ' in ' . $scope->name;

        return self::one(array_values($matches), sprintf('county identifier %s%s', $value, $scopeDescription));
    }

    /** @param State|string|null $state */
    public static function tryFromAny(string $value, $state = null): ?self
    {
        return self::tryLookup(function () use ($value, $state): self {
            return self::fromAny($value, $state);
        });
    }

    /**
     * @internal Used by State::counties().
     *
     * @return self[]
     */
    public static function forState(State $state): array
    {
        self::initialize();

        return array_values(self::$byState[$state->fips] ?? []);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'officialName' => $this->officialName,
            'type' => $this->type,
            'ansiCode' => $this->ansiCode,
            'fips' => $this->fips,
            'stateFips' => $this->stateFips,
            'countyFips' => $this->countyFips,
            'state' => $this->state->toArray(),
        ];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return mixed */
    public function __get(string $property)
    {
        switch ($property) {
            case 'name':
                return $this->name;
            case 'officialName':
                return $this->officialName;
            case 'type':
                return $this->type;
            case 'ansiCode':
                return $this->ansiCode;
            case 'fips':
                return $this->fips;
            case 'stateFips':
                return $this->stateFips;
            case 'countyFips':
                return $this->countyFips;
            case 'state':
                return $this->state;
            default:
                throw new LogicException(sprintf('Undefined read-only property %s::$%s.', self::class, $property));
        }
    }

    public function __isset(string $property): bool
    {
        return in_array($property, [
            'name', 'officialName', 'type', 'ansiCode', 'fips', 'stateFips', 'countyFips', 'state',
        ], true);
    }

    /** @param mixed $value */
    public function __set(string $property, $value): void
    {
        throw new LogicException(sprintf('Cannot write read-only property %s::$%s.', self::class, $property));
    }

    public function __unset(string $property): void
    {
        throw new LogicException(sprintf('Cannot unset read-only property %s::$%s.', self::class, $property));
    }

    public function __toString(): string
    {
        return $this->name;
    }

    private static function initialize(): void
    {
        if (self::$byFips !== null) {
            return;
        }

        $byFips = [];
        $byName = [];
        $byState = [];

        foreach (Dataset::records('counties') as $record) {
            $state = State::fromFips($record['stateFips']);
            $county = new self(
                $record['name'],
                $record['officialName'],
                $record['type'],
                $record['ansiCode'],
                $record['fips'],
                $record['stateFips'],
                $record['countyFips'],
                $state
            );

            $byFips[$county->fips] = $county;
            $byState[$county->stateFips][$county->fips] = $county;

            foreach ([$county->name, $county->officialName] as $name) {
                $key = Normalizer::name($name);
                $byName[$key][$county->fips] = $county;
            }
        }

        ksort($byFips, SORT_STRING);
        foreach ($byState as &$counties) {
            ksort($counties, SORT_STRING);
        }
        unset($counties);

        self::$byFips = $byFips;
        self::$byName = $byName;
        self::$byState = $byState;
    }

    /** @param mixed $state */
    private static function resolveState($state): State
    {
        if ($state instanceof State) {
            return $state;
        }

        if (is_string($state)) {
            return State::fromAny($state);
        }

        throw new InvalidIdentifierException('State scope must be a State object or string identifier.');
    }

    /** @return self[] */
    private static function findNameMatches(string $normalizedName, ?State $state): array
    {
        self::initialize();
        $matches = array_values(self::$byName[$normalizedName] ?? []);

        if ($state === null) {
            return $matches;
        }

        return array_values(array_filter($matches, function (self $county) use ($state): bool {
            return $county->stateFips === $state->fips;
        }));
    }

    /** @param self[] $matches */
    private static function one(array $matches, string $description): self
    {
        if ($matches === []) {
            throw new NotFoundException(sprintf('No match found for %s.', $description));
        }

        if (count($matches) > 1) {
            throw new AmbiguousMatchException(sprintf('Multiple counties match %s.', $description), $matches);
        }

        return $matches[0];
    }

    private static function tryLookup(callable $lookup): ?self
    {
        try {
            return $lookup();
        } catch (InvalidIdentifierException | NotFoundException $exception) {
            return null;
        }
    }
}
