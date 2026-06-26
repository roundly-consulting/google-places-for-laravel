<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

final readonly class AutocompleteQuery
{
    /**
     * @param  list<string>  $includedPrimaryTypes
     * @param  list<string>  $includedRegionCodes
     *
     * @throws PlacesException
     */
    public function __construct(
        public string $input = '',
        public string $language = 'en',
        public array $includedPrimaryTypes = [],
        public LocationDefinition $locationBias = new LocationDefinition,
        public LocationDefinition $locationRestriction = new LocationDefinition,
        public array $includedRegionCodes = [],
        public ?Location $origin = null,
        public ?string $sessionToken = null,
    ) {
        if (count($includedPrimaryTypes) > 5) {
            throw PlacesException::tooManyPrimaryTypes();
        }

        if (count($includedRegionCodes) > 15) {
            throw PlacesException::tooManyRegionCodes();
        }
    }

    public function withInput(string $input): self
    {
        return new self($input, $this->language, $this->includedPrimaryTypes, $this->locationBias, $this->locationRestriction, $this->includedRegionCodes, $this->origin, $this->sessionToken);
    }

    public function inLanguage(string $language): self
    {
        return new self($this->input, $language, $this->includedPrimaryTypes, $this->locationBias, $this->locationRestriction, $this->includedRegionCodes, $this->origin, $this->sessionToken);
    }

    public function ofType(string ...$types): self
    {
        return new self($this->input, $this->language, array_values($types), $this->locationBias, $this->locationRestriction, $this->includedRegionCodes, $this->origin, $this->sessionToken);
    }

    public function preferInArea(LocationDefinition $location): self
    {
        return new self($this->input, $this->language, $this->includedPrimaryTypes, $location, $this->locationRestriction, $this->includedRegionCodes, $this->origin, $this->sessionToken);
    }

    public function restrictTo(LocationDefinition $location): self
    {
        return new self($this->input, $this->language, $this->includedPrimaryTypes, $this->locationBias, $location, $this->includedRegionCodes, $this->origin, $this->sessionToken);
    }

    public function inRegions(string ...$codes): self
    {
        return new self($this->input, $this->language, $this->includedPrimaryTypes, $this->locationBias, $this->locationRestriction, array_values($codes), $this->origin, $this->sessionToken);
    }

    public function fromOrigin(?Location $origin): self
    {
        return new self($this->input, $this->language, $this->includedPrimaryTypes, $this->locationBias, $this->locationRestriction, $this->includedRegionCodes, $origin, $this->sessionToken);
    }

    public function usingSessionToken(string $token): self
    {
        return new self($this->input, $this->language, $this->includedPrimaryTypes, $this->locationBias, $this->locationRestriction, $this->includedRegionCodes, $this->origin, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function toBody(): array
    {
        $body = [
            'input' => $this->input,
            'languageCode' => $this->language,
        ];

        if (count($this->includedPrimaryTypes) > 0) {
            $body['includedPrimaryTypes'] = $this->includedPrimaryTypes;
        }

        if ($this->locationBias->value !== null) {
            $body['locationBias'] = $this->locationBias->value;
        }

        if ($this->locationRestriction->value !== null) {
            $body['locationRestriction'] = $this->locationRestriction->value;
        }

        if (count($this->includedRegionCodes) > 0) {
            $body['includedRegionCodes'] = $this->includedRegionCodes;
        }

        if ($this->origin instanceof Location) {
            $body['origin'] = [
                'latitude' => $this->origin->latitude,
                'longitude' => $this->origin->longitude,
            ];
        }

        if ($this->sessionToken !== null) {
            $body['sessionToken'] = $this->sessionToken;
        }

        return $body;
    }
}
