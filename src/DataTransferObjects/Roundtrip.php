<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use Carbon\CarbonInterval;

final readonly class Roundtrip
{
    /**
     * @param  list<Distance>  $distances
     */
    public function __construct(
        public array $distances,
        public string $humanReadableDistance,
        public int $distanceInMeters,
        public string $humanReadableDuration,
        public int $durationInSeconds,
        public string $type = 'driving',
    ) {}

    /**
     * @param  array<int, mixed>  $distances
     */
    public static function fromGoogleResponse(array $distances, string $type = 'driving'): self
    {
        $items = array_map(
            static fn (mixed $distance): Distance => Distance::fromGoogleResponse((array) $distance, $type),
            array_values($distances),
        );

        $distanceInMeters = array_sum(array_map(static fn (Distance $d): int => $d->distanceInMeters, $items));
        $durationInSeconds = array_sum(array_map(static fn (Distance $d): int => $d->durationInSeconds, $items));

        $humanReadableDuration = CarbonInterval::seconds($durationInSeconds)
            ->cascade()
            ->forHumans(short: true);

        return new self(
            distances: $items,
            humanReadableDistance: round($distanceInMeters / 1000, 2).'km',
            distanceInMeters: $distanceInMeters,
            humanReadableDuration: $humanReadableDuration,
            durationInSeconds: $durationInSeconds,
            type: $type,
        );
    }
}
