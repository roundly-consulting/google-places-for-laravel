<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class ReverseGeocodingResult
{
    /**
     * @param  list<string>  $types
     * @param  list<AddressComponent>  $components
     */
    public function __construct(
        public string $address,
        public string $placeId,
        public Geometry $geometry,
        public array $types,
        public array $components = [],
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromGoogleResponse(array $item): self
    {
        return new self(
            address: (string) $item['formatted_address'],
            placeId: (string) $item['place_id'],
            geometry: Geometry::fromGoogleResponse((array) $item['geometry']),
            types: array_map(strval(...), array_values((array) $item['types'])),
            components: array_map(
                static fn (mixed $component): AddressComponent => AddressComponent::fromGoogleResponse((array) $component),
                array_values((array) $item['address_components']),
            ),
        );
    }
}
