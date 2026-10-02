<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class Location
{
    public function __construct(public float $latitude, public float $longitude) {}

    /**
     * "lat,lng" as plain decimals. PHP prints a float below 1e-4 in scientific notation
     * (`1.0E-5`), which the Geocoding API does not accept as `latlng` — and the equator and
     * Greenwich bands are exactly where such values occur.
     */
    public function toRequest(): string
    {
        return self::decimal($this->latitude).','.self::decimal($this->longitude);
    }

    private static function decimal(float $value): string
    {
        $plain = (string) $value;

        if (stripos($plain, 'e') !== false) {
            $plain = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
        }

        return $plain === '-0' ? '0' : $plain;
    }
}
