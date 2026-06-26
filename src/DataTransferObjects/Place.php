<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class Place
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $photos  Photo resource names (pass to GooglePlaces::photoUrl()).
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public ?string $id = null,
        public ?string $formattedAddress = null,
        public array $types = [],
        public ?Geometry $geometry = null,
        public ?OpeningHours $openingHours = null,
        public array $photos = [],
        public array $raw = [],
    ) {}

    /**
     * Map a Places API (New) place resource.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromResponse(array $item): self
    {
        $displayName = (array) ($item['displayName'] ?? []);

        return new self(
            name: (string) ($displayName['text'] ?? ''),
            id: isset($item['id']) ? (string) $item['id'] : null,
            formattedAddress: isset($item['formattedAddress']) ? (string) $item['formattedAddress'] : null,
            types: array_map(strval(...), array_values((array) ($item['types'] ?? []))),
            geometry: isset($item['location'])
                ? Geometry::fromPlace((array) $item['location'], (array) ($item['viewport'] ?? []))
                : null,
            openingHours: isset($item['regularOpeningHours'])
                ? OpeningHours::fromResponse((array) $item['regularOpeningHours'])
                : null,
            photos: array_map(
                static fn (mixed $photo): string => (string) ((array) $photo)['name'],
                array_values((array) ($item['photos'] ?? [])),
            ),
            raw: $item,
        );
    }
}
