<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use Carbon\CarbonInterval;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

final readonly class Distance
{
    public TravelMode $type;

    public function __construct(
        public string $humanReadableDistance,
        public int $distanceInMeters,
        public string $humanReadableDuration,
        public int $durationInSeconds,
        TravelMode|string $type = TravelMode::Driving,
    ) {
        $this->type = $type instanceof TravelMode ? $type : TravelMode::from($type);
    }

    public function isDriving(): bool
    {
        return $this->type === TravelMode::Driving;
    }

    public function isWalking(): bool
    {
        return $this->type === TravelMode::Walking;
    }

    /**
     * Map a single Routes API `computeRouteMatrix` element. The Routes API does
     * not return human-readable strings, so they are formatted by the package.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromRoutesElement(array $item, TravelMode $type = TravelMode::Driving): self
    {
        $meters = (int) ($item['distanceMeters'] ?? 0);
        $seconds = (int) (float) rtrim((string) ($item['duration'] ?? '0s'), 's');

        return new self(
            humanReadableDistance: self::metersToHuman($meters),
            distanceInMeters: $meters,
            humanReadableDuration: CarbonInterval::seconds($seconds)->cascade()->forHumans(short: true),
            durationInSeconds: $seconds,
            type: $type,
        );
    }

    public static function metersToHuman(int $meters): string
    {
        return round($meters / 1000, 1).' km';
    }
}
