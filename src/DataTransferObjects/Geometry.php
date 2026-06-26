<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class Geometry
{
    public function __construct(
        public Location $location,
        public Location $viewportNorthEast,
        public Location $viewportSouthWest,
    ) {}

    /**
     * Map the Geocoding API shape (`location.{lat,lng}`,
     * `viewport.{northeast,southwest}`).
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromResponse(array $item): self
    {
        $location = (array) $item['location'];
        $viewport = (array) $item['viewport'];
        $northeast = (array) $viewport['northeast'];
        $southwest = (array) $viewport['southwest'];

        return new self(
            location: new Location((float) $location['lat'], (float) $location['lng']),
            viewportNorthEast: new Location((float) $northeast['lat'], (float) $northeast['lng']),
            viewportSouthWest: new Location((float) $southwest['lat'], (float) $southwest['lng']),
        );
    }

    /**
     * Map the Places API (New) shape (`location.{latitude,longitude}` and
     * `viewport.{high,low}`). When the viewport is absent the point is used
     * for both corners.
     *
     * @param  array<string, mixed>  $location
     * @param  array<string, mixed>  $viewport
     */
    public static function fromPlace(array $location, array $viewport = []): self
    {
        $high = (array) ($viewport['high'] ?? $location);
        $low = (array) ($viewport['low'] ?? $location);

        return new self(
            location: new Location((float) $location['latitude'], (float) $location['longitude']),
            viewportNorthEast: new Location((float) $high['latitude'], (float) $high['longitude']),
            viewportSouthWest: new Location((float) $low['latitude'], (float) $low['longitude']),
        );
    }
}
