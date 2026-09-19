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
 * An immutable state or state-equivalent record from the Census Gazetteer.
 *
 * @property-read string $name
 * @property-read string $fips
 * @property-read string $usps
 * @property-read string $abbreviation Alias of $usps
 */
final class State implements JsonSerializable
{
    /** @var string */
    private $name;

    /** @var string */
    private $fips;

    /** @var string */
    private $usps;

    /** @var array<string,self>|null */
    private static $byFips;

    /** @var array<string,self>|null */
    private static $byUsps;

    /** @var array<string,array<string,self>>|null */
    private static $byName;

    private function __construct(string $name, string $fips, string $usps)
    {
        $this->name = $name;
        $this->fips = $fips;
        $this->usps = $usps;
    }

    /** @return self[] */
    public static function all(): array
    {
        self::initialize();

        return array_values(self::$byFips ?? []);
    }

    public static function fromFips(string $fips): self
    {
        $fips = Normalizer::digits($fips, 2, 'State FIPS code');
        self::initialize();

        if (!isset(self::$byFips[$fips])) {
            throw new NotFoundException(sprintf('No state found with FIPS code: %s', $fips));
        }

        return self::$byFips[$fips];
    }

    public static function tryFromFips(string $fips): ?self
    {
        return self::tryLookup(function () use ($fips): self {
            return self::fromFips($fips);
        });
    }

    public static function fromUsps(string $usps): self
    {
        $usps = Normalizer::letters($usps, 2, 'State USPS abbreviation');
        self::initialize();

        if (!isset(self::$byUsps[$usps])) {
            throw new NotFoundException(sprintf('No state found with USPS abbreviation: %s', $usps));
        }

        return self::$byUsps[$usps];
    }

    public static function tryFromUsps(string $usps): ?self
    {
        return self::tryLookup(function () use ($usps): self {
            return self::fromUsps($usps);
        });
    }

    public static function fromAbbr(string $abbreviation): self
    {
        return self::fromUsps($abbreviation);
    }

    public static function tryFromAbbr(string $abbreviation): ?self
    {
        return self::tryFromUsps($abbreviation);
    }

    public static function fromName(string $name): self
    {
        $key = Normalizer::name($name);
        self::initialize();
        $matches = isset(self::$byName[$key]) ? array_values(self::$byName[$key]) : [];

        return self::one($matches, sprintf('state name %s', $name));
    }

    public static function tryFromName(string $name): ?self
    {
        return self::tryLookup(function () use ($name): self {
            return self::fromName($name);
        });
    }

    public static function fromAny(string $value): self
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidIdentifierException('A state identifier must not be empty.');
        }

        self::initialize();
        $matches = [];

        if (preg_match('/^\d{2}$/D', $value) && isset(self::$byFips[$value])) {
            $matches[$value] = self::$byFips[$value];
        }

        $usps = strtoupper($value);
        if (strlen($usps) === 2 && ctype_alpha($usps) && isset(self::$byUsps[$usps])) {
            $matches[self::$byUsps[$usps]->fips] = self::$byUsps[$usps];
        }

        $name = Normalizer::name($value);
        foreach (self::$byName[$name] ?? [] as $fips => $state) {
            $matches[$fips] = $state;
        }

        return self::one(array_values($matches), sprintf('state identifier %s', $value));
    }

    public static function tryFromAny(string $value): ?self
    {
        return self::tryLookup(function () use ($value): self {
            return self::fromAny($value);
        });
    }

    /** @return County[] */
    public function counties(): array
    {
        return County::forState($this);
    }

    public function countyFromName(string $name): County
    {
        return County::fromName($name, $this);
    }

    /** @return County[] */
    public function findCounties(string $name): array
    {
        return County::findByName($name, $this);
    }

    public function countyFromFips(string $countyFips): County
    {
        $countyFips = Normalizer::digits($countyFips, 3, 'County FIPS code');

        return County::fromFips($this->fips . $countyFips);
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'fips' => $this->fips,
            'usps' => $this->usps,
            'abbreviation' => $this->usps,
        ];
    }

    /** @return array<string,string> */
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
            case 'fips':
                return $this->fips;
            case 'usps':
            case 'abbreviation':
                return $this->usps;
            default:
                throw new LogicException(sprintf('Undefined read-only property %s::$%s.', self::class, $property));
        }
    }

    public function __isset(string $property): bool
    {
        return in_array($property, ['name', 'fips', 'usps', 'abbreviation'], true);
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
        $byUsps = [];
        $byName = [];

        foreach (Dataset::records('states') as $record) {
            $state = new self($record['name'], $record['fips'], $record['usps']);

            $byFips[$state->fips] = $state;
            $byUsps[$state->usps] = $state;
            $name = Normalizer::name($state->name);
            $byName[$name][$state->fips] = $state;
        }

        ksort($byFips, SORT_STRING);
        self::$byFips = $byFips;
        self::$byUsps = $byUsps;
        self::$byName = $byName;
    }

    /** @param self[] $matches */
    private static function one(array $matches, string $description): self
    {
        if ($matches === []) {
            throw new NotFoundException(sprintf('No match found for %s.', $description));
        }

        if (count($matches) > 1) {
            throw new AmbiguousMatchException(sprintf('Multiple states match %s.', $description), $matches);
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
