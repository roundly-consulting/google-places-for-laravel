<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class ReverseGeocodingQuery
{
    /**
     * @param  list<string>  $resultTypes
     * @param  list<string>  $locationTypes
     */
    public function __construct(
        public Location $location,
        public array $resultTypes = [],
        public array $locationTypes = [],
        public string $language = 'en',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toRequest(): array
    {
        $query = [
            'latlng' => $this->location->toRequest(),
            'language' => $this->language,
        ];

        if (count($this->resultTypes) > 0) {
            $query['result_type'] = implode('|', $this->resultTypes);
        }

        if (count($this->locationTypes) > 0) {
            $query['location_type'] = implode('|', $this->locationTypes);
        }

        return $query;
    }
}
