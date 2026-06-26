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
     * @param  array<string, mixed>  $item
     */
    public static function fromGoogleResponse(array $item): self
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
}
