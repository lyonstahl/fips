<?php

declare(strict_types=1);

namespace LyonStahl\Fips;

use LyonStahl\Fips\Exception\StateException;

class State
{
    /**
     * @var string Source file for the states
     */
    private static $source = __DIR__.'/../data/states.php';

    /** @var string */
    public $name;

    /** @var string Two-letter abbreviation (ANSI) */
    public $abbreviation;

    /** @var string Two-digit FIPS code (ANSI) */
    public $fips;

    /** @var string ISO 3166-2 code */
    public $iso;

    /** @var string U.S. Postal Service code */
    public $usps;

    /** @var string U.S. Coast Guard code */
    public $uscg;

    public function __construct(string $name, string $abbreviation, string $fips, string $iso, string $usps, string $uscg)
    {
        $this->name = $name;
        $this->abbreviation = $abbreviation;
        $this->fips = $fips;
        $this->iso = $iso;
        $this->usps = $usps;
        $this->uscg = $uscg;
    }

    /**
     * Read all counties from the packaged JSON file.
     *
     * @return array<string,string[]>
     */
    public static function read(): array
    {
        return include self::$source;
    }

    /**
     * Get all states.
     *
     * @return static[]
     */
    public static function all(): array
    {
        return array_map(function ($state) {
            return self::fromArray($state);
        }, self::read());
    }

    /** @var string */
    public function getCounties(): array
    {
        $data = County::read();

        if (!isset($data[$this->fips])) {
            return [];
        }

        return array_map(function ($county) {
            return new County($county['name'], $county['abbreviation'], $county['fips'], $this);
        }, $data[$this->fips]);
    }

    /**
     * Get a state by any identifier. Function will attempt to guess the type of identifier.
     *
     * @throws StateException
     */
    public static function fromAny(string $value): self
    {
        try {
            if (self::isFips($value)) {
                return self::fromFips($value);
            }

            if (self::isAbbr($value)) {
                return self::fromAbbr($value);
            }

            return self::fromName($value);
        } catch (StateException $e) {
            throw StateException::unableToGuess($e);
        }
    }

    /**
     * Get a state by FIPS code.
     *
     * @throws StateException
     */
    public static function fromFips(string $fips): self
    {
        if (!self::isFips($fips)) {
            throw StateException::invalidFipsCode($fips);
        }

        foreach (self::read() as $state) {
            if ($state['fips'] === $fips) {
                return self::fromArray($state);
            }
        }

        throw StateException::invalidFipsCode($fips);
    }

    /**
     * Get a state by abbreviation.
     *
     * @throws StateException
     */
    public static function fromAbbr(string $abbreviation): self
    {
        if (!self::isAbbr($abbreviation)) {
            throw StateException::invalidAbbreviation($abbreviation);
        }

        $abbreviation = strtoupper($abbreviation);

        foreach (self::read() as $state) {
            if ($state['abbreviation'] === $abbreviation) {
                return self::fromArray($state);
            }
        }

        throw StateException::invalidAbbreviation($abbreviation);
    }

    /**
     * Get a state by name.
     *
     * @throws StateException
     */
    public static function fromName(string $name): self
    {
        $name = strtolower(trim($name));

        foreach (self::read() as $state) {
            if ($name === strtolower($state['name'])) {
                return self::fromArray($state);
            }
        }

        throw StateException::invalidName($name);
    }

    /**
     * Create a state from an array. (for internal use).
     *
     * @throws StateException
     */
    private static function fromArray(array $state): self
    {
        return new self(
            $state['name'],
            $state['abbreviation'],
            $state['fips'],
            $state['iso'],
            $state['usps'],
            $state['uscg']
        );
    }

    /**
     * Check if a value is a valid FIPS state code.
     */
    private static function isFips(string $value): bool
    {
        return strlen($value) === 2 && is_numeric($value);
    }

    /**
     * Check if a value is a valid state abbreviation.
     */
    private static function isAbbr(string $value): bool
    {
        return strlen($value) === 2 && ctype_alpha($value);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
