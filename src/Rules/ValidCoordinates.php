<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;

/**
 * Validates a latitude/longitude pair: latitude in [-90, 90] and longitude in
 * [-180, 180]. Accepts a Location, an array ([lat, lng] or
 * ['lat' => …, 'lng' => …]), or a "lat,lng" string.
 */
final class ValidCoordinates implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        [$latitude, $longitude] = $this->extract($value);

        if ($latitude === null || $longitude === null) {
            $fail('The :attribute must be a valid latitude/longitude pair.');

            return;
        }

        if ($latitude < -90.0 || $latitude > 90.0) {
            $fail('The :attribute latitude must be between -90 and 90.');
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            $fail('The :attribute longitude must be between -180 and 180.');
        }
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function extract(mixed $value): array
    {
        if ($value instanceof Location) {
            return [$value->latitude, $value->longitude];
        }

        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value, 2);
        }

        if (is_array($value)) {
            $latitude = $value['lat'] ?? $value['latitude'] ?? $value[0] ?? null;
            $longitude = $value['lng'] ?? $value['longitude'] ?? $value[1] ?? null;

            if (is_numeric($latitude) && is_numeric($longitude)) {
                return [(float) $latitude, (float) $longitude];
            }
        }

        return [null, null];
    }
}
