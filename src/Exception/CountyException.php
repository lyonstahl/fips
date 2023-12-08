<?php

declare(strict_types=1);

namespace LyonStahl\Fips\Exception;

class CountyException extends \Exception
{
    public static function invalidFipsCode(string $sample): self
    {
        return new self("No county found with FIPS code: $sample", 1);
    }

    public static function invalidAbbreviation(string $sample): self
    {
        return new self("No county found with abbreviation: $sample", 2);
    }

    public static function invalidName(string $sample): self
    {
        return new self("No county found with name: $sample", 3);
    }

    public static function unableToGuess(CountyException $e): self
    {
        $type = match ($e->getCode()) {
            1 => 'FIPS code',
            2 => 'abbreviation',
            3 => 'name',
        };

        return new self("Unable to guess county identifier. Assumed $type was passed but failed to find a county.", 4, $e);
    }
}
