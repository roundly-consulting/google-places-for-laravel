<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

/**
 * The answer to one origin → several destinations (`DistanceQuery` with `MultipleLocations`):
 * one separate trip from the origin to each destination. The trips are not chained into a
 * route, so there is deliberately no total — adding them up would describe no journey anyone
 * makes.
 */
final readonly class MultipleDistances
{
    public TravelMode $type;

    /**
     * @param  list<Distance>  $distances  origin → destination i, in the order the destinations were given
     */
    public function __construct(
        public array $distances,
        TravelMode|string $type = TravelMode::Driving,
    ) {
        $this->type = $type instanceof TravelMode ? $type : TravelMode::from($type);
    }

    /**
     * Map Routes API `computeRouteMatrix` elements (one origin, already in destination
     * order) into one distance per destination.
     *
     * @param  array<int, mixed>  $elements
     */
    public static function fromRoutesElements(array $elements, TravelMode $type = TravelMode::Driving): self
    {
        return new self(
            distances: array_map(
                static fn (mixed $element): Distance => Distance::fromRoutesElement((array) $element, $type),
                array_values($elements),
            ),
            type: $type,
        );
    }
}
