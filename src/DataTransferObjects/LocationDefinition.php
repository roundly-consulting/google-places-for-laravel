<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class LocationDefinition
{
    /**
     * @param  array<string, mixed>|null  $value  The Places API (New) location body fragment.
     */
    public function __construct(
        public ?array $value = null,
    ) {}

    public function circle(Location $center, int $radius = 5000): self
    {
        return new self([
            'circle' => [
                'center' => [
                    'latitude' => $center->latitude,
                    'longitude' => $center->longitude,
                ],
                'radius' => $radius,
            ],
        ]);
    }

    public function rectangle(Location $low, Location $high): self
    {
        return new self([
            'rectangle' => [
                'low' => [
                    'latitude' => $low->latitude,
                    'longitude' => $low->longitude,
                ],
                'high' => [
                    'latitude' => $high->latitude,
                    'longitude' => $high->longitude,
                ],
            ],
        ]);
    }
}
