<?php

declare(strict_types=1);

namespace LyonStahl\Fips\Internal;

use LyonStahl\Fips\Exception\InvalidIdentifierException;

final class Normalizer
{
    private function __construct()
    {
    }

    public static function name(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value));
        if ($value === null || $value === '') {
            throw new InvalidIdentifierException('A name must not be empty.');
        }

        return mb_strtolower($value, 'UTF-8');
    }

    public static function digits(string $value, int $length, string $label): string
    {
        $value = trim($value);
        if (!preg_match('/^\d{' . $length . '}$/D', $value)) {
            throw new InvalidIdentifierException(sprintf('%s must contain exactly %d digits.', $label, $length));
        }

        return $value;
    }

    public static function letters(string $value, int $length, string $label): string
    {
        $value = trim($value);
        if (strlen($value) !== $length || !ctype_alpha($value)) {
            throw new InvalidIdentifierException(sprintf('%s must contain exactly %d letters.', $label, $length));
        }

        return strtoupper($value);
    }
}
