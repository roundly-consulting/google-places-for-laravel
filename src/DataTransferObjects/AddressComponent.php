<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class AddressComponent
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public string $longName,
        public string $shortName,
        public array $types,
    ) {}

    /**
     * Map a Geocoding API `address_components[]` entry (`long_name`/`short_name`).
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromResponse(array $item): self
    {
        return new self(
            longName: (string) $item['long_name'],
            shortName: (string) $item['short_name'],
            types: array_map(strval(...), array_values((array) $item['types'])),
        );
    }

    /**
     * Map a Places API (New) `addressComponents[]` entry (`longText`/`shortText`).
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromPlace(array $item): self
    {
        $longText = (string) ($item['longText'] ?? '');

        return new self(
            longName: $longText,
            shortName: (string) ($item['shortText'] ?? $longText),
            types: array_map(strval(...), array_values((array) ($item['types'] ?? []))),
        );
    }
}
