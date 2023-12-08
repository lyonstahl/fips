<?php

declare(strict_types=1);

namespace LyonStahl\Fips;

use LyonStahl\Fips\Exception\CountyException;

class County
{
    /**
     * @var string Source file for the counties
     *
     * Keep data in JSON for performance resons
     */
    private static $source = __DIR__.'/../data/counties.json';

    /** @var string */
    public $name;

    /** @var string|null Two-letter abbreviation (if applicable) */
    public $abbreviation;

    /** @var string Two(Three)-digit FIPS code (ANSI) */
    public $fips;

    /** @var State State object */
    public $state;

    public function __construct(string $name, ?string $abbreviation, string $fips, $state = null)
    {
        $this->name = $name;
        $this->abbreviation = $abbreviation;
        $this->fips = $fips;

        if ($state instanceof State) {
            $this->state = $state;
        } else {
            $this->state = State::fromFips((string) $state);
        }
    }

    /**
     * Read all counties from the packaged JSON file.
     *
     * @return array<string,string[]>
     */
    public static function read(): array
    {
        return json_decode(file_get_contents(self::$source), true);
    }

    /**
     * Get all counties.
     *
     * @return County[]
     */
    public static function all(): array
    {
        $data = self::read();

        $result = [];
        foreach ($data as $state => $counties) {
            foreach ($counties as $county) {
                $result[] = new static($county['name'], $county['abbreviation'], $county['fips'], $state);
            }
        }

        return $result;
    }

    /**
     * Get a county by any identifier. Function will attempt to guess the type of identifier.
     *
     * @throws CountyException
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
        } catch (CountyException $e) {
            throw CountyException::unableToGuess($e);
        }
    }

    /**
     * Get a county by FIPS code. (5-digit code, including state code).
     *
     * @throws CountyException
     */
    public static function fromFips(string $fips): self
    {
        if (!self::isFips($fips)) {
            throw CountyException::invalidFipsCode($fips);
        }

        $data = self::read();

        // Get the state fips from the first 2 characters
        $stateFips = substr($fips, 0, 2);

        // Get the county fips from the last 3 characters
        $countyFips = substr($fips, 2, 3);

        foreach ($data as $state => $counties) {
            foreach ($counties as $county) {
                if ($county['fips'] === $countyFips && $stateFips === $state) {
                    return new static($county['name'], $county['abbreviation'], $county['fips'], $state);
                }
            }
        }

        throw CountyException::invalidFipsCode($fips);
    }

    /**
     * Get a county by abbreviation.
     *
     * @throws CountyException
     */
    public static function fromAbbr(string $abbreviation): self
    {
        if (!self::isAbbr($abbreviation)) {
            throw CountyException::invalidAbbreviation($abbreviation);
        }

        $abbreviation = strtoupper($abbreviation);

        $data = self::read();
        foreach ($data as $state => $counties) {
            foreach ($counties as $county) {
                if ($county['abbreviation'] === $abbreviation) {
                    return new static($county['name'], $county['abbreviation'], $county['fips'], $state);
                }
            }
        }

        throw CountyException::invalidAbbreviation($abbreviation);
    }

    /**
     * Get a county by name.
     *
     * @throws CountyException
     */
    public static function fromName(string $name): self
    {
        $name = strtolower(trim($name));

        $data = self::read();
        foreach ($data as $state => $counties) {
            foreach ($counties as $county) {
                if (strtolower($county['name']) === $name) {
                    return new static($county['name'], $county['abbreviation'], $county['fips'], $state);
                }
            }
        }

        throw CountyException::invalidName($name);
    }

    /**
     * Check if a value is a valid FIPS county code.
     */
    private static function isFips(string $value): bool
    {
        return strlen($value) === 5 && is_numeric($value);
    }

    /**
     * Check if a value is a valid county abbreviation.
     */
    private static function isAbbr(string $value): bool
    {
        return (strlen($value) === 2 || strlen($value) === 3) && ctype_alpha($value);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
