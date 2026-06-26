<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Events;

final readonly class PlacesResponseReceived
{
    public function __construct(
        public string $endpoint,
        public int $httpStatus,
        public ?string $googleStatus = null,
        public ?int $durationMs = null,
    ) {}
}
