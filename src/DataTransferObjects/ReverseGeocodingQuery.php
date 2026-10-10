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
        public bool $fresh = false,
    ) {}

    /**
     * Skip the cache read for this lookup: ask Google, then write the answer back to the
     * cache. The flag never reaches Google and is not part of the cache key.
     */
    public function fresh(bool $fresh = true): self
    {
        return new self($this->location, $this->resultTypes, $this->locationTypes, $this->language, $fresh);
    }

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
