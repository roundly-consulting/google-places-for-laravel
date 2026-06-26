<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use Illuminate\Support\Carbon;

final class DistanceQuery
{
    public function __construct(
        public Location $from,
        public Location|MultipleLocations $to,
        public string $type = 'driving',
        public ?Carbon $departureAt = null,
    ) {}

    public function driving(): self
    {
        $this->type = 'driving';

        return $this;
    }

    public function walking(): self
    {
        $this->type = 'walking';

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRequest(): array
    {
        $request = [
            'destinations' => $this->to->toRequest(),
            'origins' => $this->from->toRequest(),
            'mode' => $this->type,
            'language' => 'en',
        ];

        if ($this->departureAt !== null) {
            $request['departure_time'] = $this->departureAt->timestamp;
        }

        return $request;
    }
}
