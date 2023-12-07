<?php

declare(strict_types=1);

namespace LyonStahl\Fips;

use LyonStahl\Fips\Exception\CountyException;
use LyonStahl\Fips\Exception\StateException;

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

    /**
     * @var string|null Two-letter abbreviation (if applicable)
     */
    public $abbreviation;

    /**
     * @var string Two-digit FIPS code (ANSI)
     */
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
     * Get a county by FIPS code. (6-digit code, including state code).
     *
     * @throws StateException
     */
    public static function fromFips(string $fips): self
    {
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
     * @throws StateException
     */
    public static function fromAbbr(string $abbreviation): self
    {
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
     * @throws StateException
     */
    public static function fromName(string $name): self
    {
        $data = self::read();

        foreach ($data as $state => $counties) {
            foreach ($counties as $county) {
                if ($county['name'] === $name) {
                    return new static($county['name'], $county['abbreviation'], $county['fips'], $state);
                }
            }
        }

        throw CountyException::invalidName($name);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
