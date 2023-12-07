<?php

declare(strict_types=1);

namespace LyonStahl\Fips\Exception;

class StateException extends \Exception
{
    public static function invalidFipsCode(string $sample): self
    {
        return new self("No state found with FIPS code: $sample");
    }

    public static function invalidAbbreviation(string $sample): self
    {
        return new self("No state found with abbreviation: $sample");
    }

    public static function invalidName(string $sample): self
    {
        return new self("No state found with name: $sample");
    }
}
