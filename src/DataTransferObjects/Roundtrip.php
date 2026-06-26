<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use Carbon\CarbonInterval;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

final readonly class Roundtrip
{
    public TravelMode $type;

    /**
     * @param  list<Distance>  $distances
     */
    public function __construct(
        public array $distances,
        public string $humanReadableDistance,
        public int $distanceInMeters,
        public string $humanReadableDuration,
        public int $durationInSeconds,
        TravelMode|string $type = TravelMode::Driving,
    ) {
        $this->type = $type instanceof TravelMode ? $type : TravelMode::from($type);
    }

    /**
     * Map several Routes API `computeRouteMatrix` elements into one trip total.
     *
     * @param  array<int, mixed>  $elements
     */
    public static function fromRoutesElements(array $elements, TravelMode $type = TravelMode::Driving): self
    {
        $items = array_map(
            static fn (mixed $element): Distance => Distance::fromRoutesElement((array) $element, $type),
            array_values($elements),
        );

        $distanceInMeters = array_sum(array_map(static fn (Distance $d): int => $d->distanceInMeters, $items));
        $durationInSeconds = array_sum(array_map(static fn (Distance $d): int => $d->durationInSeconds, $items));

        return new self(
            distances: $items,
            humanReadableDistance: Distance::metersToHuman($distanceInMeters),
            distanceInMeters: $distanceInMeters,
            humanReadableDuration: CarbonInterval::seconds($durationInSeconds)->cascade()->forHumans(short: true),
            durationInSeconds: $durationInSeconds,
            type: $type,
        );
    }
}
