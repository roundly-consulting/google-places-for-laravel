<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

final readonly class NearbySearchQuery
{
    public const MIN_RADIUS = 0;

    public const MAX_RADIUS = 50000;

    /**
     * @param  list<string>  $includedTypes
     * @param  list<string>  $excludedTypes
     *
     * @throws PlacesException
     */
    public function __construct(
        public Location $location,
        public int $radius = 5000,
        public array $includedTypes = [],
        public array $excludedTypes = [],
        public ?int $maxResultCount = null,
        public ?string $rankPreference = null,
        public string $language = 'en',
    ) {
        if ($radius < self::MIN_RADIUS || $radius > self::MAX_RADIUS) {
            throw PlacesException::invalidRadius(self::MIN_RADIUS, self::MAX_RADIUS);
        }
    }

    public function withRadius(int $radius): self
    {
        return new self($this->location, $radius, $this->includedTypes, $this->excludedTypes, $this->maxResultCount, $this->rankPreference, $this->language);
    }

    public function withinTypes(string ...$types): self
    {
        return new self($this->location, $this->radius, array_values($types), $this->excludedTypes, $this->maxResultCount, $this->rankPreference, $this->language);
    }

    public function excludingTypes(string ...$types): self
    {
        return new self($this->location, $this->radius, $this->includedTypes, array_values($types), $this->maxResultCount, $this->rankPreference, $this->language);
    }

    public function take(int $maxResultCount): self
    {
        return new self($this->location, $this->radius, $this->includedTypes, $this->excludedTypes, $maxResultCount, $this->rankPreference, $this->language);
    }

    public function rankBy(string $rankPreference): self
    {
        return new self($this->location, $this->radius, $this->includedTypes, $this->excludedTypes, $this->maxResultCount, $rankPreference, $this->language);
    }

    public function inLanguage(string $language): self
    {
        return new self($this->location, $this->radius, $this->includedTypes, $this->excludedTypes, $this->maxResultCount, $this->rankPreference, $language);
    }

    /**
     * @return array<string, mixed>
     */
    public function toBody(): array
    {
        $body = [
            'languageCode' => $this->language,
            'locationRestriction' => [
                'circle' => [
                    'center' => [
                        'latitude' => $this->location->latitude,
                        'longitude' => $this->location->longitude,
                    ],
                    'radius' => $this->radius,
                ],
            ],
        ];

        if (count($this->includedTypes) > 0) {
            $body['includedTypes'] = $this->includedTypes;
        }

        if (count($this->excludedTypes) > 0) {
            $body['excludedTypes'] = $this->excludedTypes;
        }

        if ($this->maxResultCount !== null) {
            $body['maxResultCount'] = $this->maxResultCount;
        }

        if ($this->rankPreference !== null) {
            $body['rankPreference'] = $this->rankPreference;
        }

        return $body;
    }
}
