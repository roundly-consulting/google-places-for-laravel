<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use Carbon\CarbonInterval;

final readonly class Distance
{
    public function __construct(
        public string $humanReadableDistance,
        public int $distanceInMeters,
        public string $humanReadableDuration,
        public int $durationInSeconds,
        public string $type = 'driving',
    ) {}

    public function isDriving(): bool
    {
        return $this->type === 'driving';
    }

    public function isWalking(): bool
    {
        return $this->type === 'walking';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromGoogleResponse(array $item, string $type = 'driving'): self
    {
        $distance = (array) $item['distance'];
        $duration = array_key_exists('duration_in_traffic', $item)
            ? (array) $item['duration_in_traffic']
            : (array) $item['duration'];

        $humanReadableDuration = CarbonInterval::seconds((int) $duration['value'])
            ->cascade()
            ->forHumans(short: true);

        return new self(
            humanReadableDistance: (string) $distance['text'],
            distanceInMeters: (int) $distance['value'],
            humanReadableDuration: $humanReadableDuration,
            durationInSeconds: (int) $duration['value'],
            type: $type,
        );
    }
}
