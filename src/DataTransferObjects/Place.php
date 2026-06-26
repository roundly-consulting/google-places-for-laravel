<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class Place
{
    /**
     * @param  list<string>  $types
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public array $types = [],
        public ?Geometry $geometry = null,
        public ?OpeningHours $openingHours = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromGoogleResponse(array $item): self
    {
        return new self(
            name: (string) $item['name'],
            types: array_map(strval(...), array_values((array) ($item['types'] ?? []))),
            geometry: array_key_exists('geometry', $item) ? Geometry::fromGoogleResponse((array) $item['geometry']) : null,
            openingHours: array_key_exists('opening_hours', $item) ? OpeningHours::fromGoogleResponse((array) $item['opening_hours']) : null,
            raw: $item,
        );
    }
}
