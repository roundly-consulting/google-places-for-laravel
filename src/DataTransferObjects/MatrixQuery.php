<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use Illuminate\Support\Carbon;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

final readonly class MatrixQuery
{
    public TravelMode $type;

    /**
     * @param  list<Location>  $origins
     * @param  list<Location>  $destinations
     */
    public function __construct(
        public array $origins,
        public array $destinations,
        TravelMode|string $type = TravelMode::Driving,
        public ?Carbon $departureAt = null,
    ) {
        $this->type = $type instanceof TravelMode ? $type : TravelMode::from($type);
    }

    public function driving(): self
    {
        return $this->travellingBy(TravelMode::Driving);
    }

    public function walking(): self
    {
        return $this->travellingBy(TravelMode::Walking);
    }

    public function bicycling(): self
    {
        return $this->travellingBy(TravelMode::Bicycling);
    }

    public function transit(): self
    {
        return $this->travellingBy(TravelMode::Transit);
    }

    public function travellingBy(TravelMode $mode): self
    {
        return new self($this->origins, $this->destinations, $mode, $this->departureAt);
    }

    public function departingAt(?Carbon $departureAt): self
    {
        return new self($this->origins, $this->destinations, $this->type, $departureAt);
    }

    /**
     * Build the Routes API `computeRouteMatrix` body.
     *
     * @return array<string, mixed>
     */
    public function toRoutesBody(): array
    {
        $body = [
            'origins' => array_map($this->waypoint(...), $this->origins),
            'destinations' => array_map($this->waypoint(...), $this->destinations),
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
