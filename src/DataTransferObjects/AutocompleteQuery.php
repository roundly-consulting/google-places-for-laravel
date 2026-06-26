<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final class AutocompleteQuery
{
    /**
     * @param  list<string>  $components
     * @param  list<string>  $types
     */
    public function __construct(
        public string $query = '',
        public array $components = [],
        public string $language = 'en',
        public LocationDefinition $locationBias = new LocationDefinition('ipbias'),
        public LocationDefinition $locationRestriction = new LocationDefinition,
        public ?int $offset = null,
        public ?Location $origin = null,
        public ?string $region = null,
        public ?string $sessionToken = null,
        public bool $strictBounds = false,
        public array $types = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toRequest(): array
    {
        $query = [
            'input' => $this->query,
            'language' => $this->language,
        ];

        if (count($this->components) > 0) {
            $query['components'] = implode('|', $this->components);
        }

        if (count($this->types) > 0) {
            $query['types'] = implode('|', $this->types);
        }

        if ($this->locationBias->value !== null) {
            $query['locationbias'] = $this->locationBias->value;
        }

        if ($this->locationRestriction->value !== null) {
            $query['locationrestriction'] = $this->locationRestriction->value;
        }

        if ($this->offset !== null) {
            $query['offset'] = $this->offset;
        }

        if ($this->origin instanceof Location) {
            $query['origin'] = $this->origin->toRequest();
        }

        if ($this->region !== null) {
            $query['region'] = $this->region;
        }

        if ($this->sessionToken !== null) {
            $query['sessiontoken'] = $this->sessionToken;
        }

        if ($this->strictBounds) {
            $query['strictbounds'] = $this->strictBounds;
        }

        return $query;
    }

    public function query(string $query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @param  list<string>  $components
     */
    public function withComponents(array $components): self
    {
        $this->components = $components;

        return $this;
    }

    public function inLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function preferInArea(LocationDefinition $location): self
    {
        $this->locationBias = $location;

        return $this;
    }

    public function preferInAreaByIpAddress(): self
    {
        $this->locationBias = new LocationDefinition('ipbias');

        return $this;
    }

    public function restrictLocationBy(LocationDefinition $location): self
    {
        $this->locationRestriction = $location;

        return $this;
    }

    public function withoutLocationRestriction(): self
    {
        $this->locationRestriction = new LocationDefinition;

        return $this;
    }

    public function ofType(string ...$type): self
    {
        $this->types = array_values($type);

        return $this;
    }

    public function withStrictBoundary(bool $value = true): self
    {
        $this->strictBounds = $value;

        return $this;
    }

    public function usingSessionToken(string $token): self
    {
        $this->sessionToken = $token;

        return $this;
    }

    public function inRegion(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function fromOrigin(?Location $location): self
    {
        $this->origin = $location;

        return $this;
    }

    public function usingOffset(?int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }
}
