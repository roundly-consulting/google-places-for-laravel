<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use Illuminate\Support\Carbon;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

final readonly class DistanceQuery
{
    public TravelMode $type;

    public function __construct(
        public Location $from,
        public Location|MultipleLocations $to,
        TravelMode|string $type = TravelMode::Driving,
        public ?Carbon $departureAt = null,
        public bool $fresh = false,
    ) {
        $this->type = $type instanceof TravelMode ? $type : TravelMode::from($type);
    }

    public function driving(): self
    {
        return new self($this->from, $this->to, TravelMode::Driving, $this->departureAt, $this->fresh);
    }

    public function walking(): self
    {
        return new self($this->from, $this->to, TravelMode::Walking, $this->departureAt, $this->fresh);
    }

    public function bicycling(): self
    {
        return new self($this->from, $this->to, TravelMode::Bicycling, $this->departureAt, $this->fresh);
    }

    public function transit(): self
    {
        return new self($this->from, $this->to, TravelMode::Transit, $this->departureAt, $this->fresh);
    }

    public function travellingBy(TravelMode $mode): self
    {
        return new self($this->from, $this->to, $mode, $this->departureAt, $this->fresh);
    }

    public function departingAt(?Carbon $departureAt): self
    {
        return new self($this->from, $this->to, $this->type, $departureAt, $this->fresh);
    }

    /**
     * Skip the cache read for this lookup: ask Google, then write the answer back to the
     * cache. The flag never reaches Google and is not part of the cache key.
     */
    public function fresh(bool $fresh = true): self
    {
        return new self($this->from, $this->to, $this->type, $this->departureAt, $fresh);
    }

    /**
     * Build the Routes API `computeRouteMatrix` body.
     *
     * @return array<string, mixed>
     */
    public function toRoutesBody(): array
    {
        $destinations = $this->to instanceof MultipleLocations
            ? $this->to->map(fn (Location $location): array => $this->waypoint($location))->all()
            : [$this->waypoint($this->to)];

        $body = [
            'origins' => [$this->waypoint($this->from)],
            'destinations' => array_values($destinations),
            'travelMode' => $this->type->routesValue(),
        ];

        if ($this->departureAt !== null) {
            $body['departureTime'] = $this->departureAt->toIso8601ZuluString();

            if ($this->type === TravelMode::Driving) {
                $body['routingPreference'] = 'TRAFFIC_AWARE';
            }
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function waypoint(Location $location): array
    {
        return [
            'waypoint' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $location->latitude,
                        'longitude' => $location->longitude,
                    ],
                ],
            ],
        ];
    }
}
