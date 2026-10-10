<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class GeocodingQuery
{
    /**
     * @param  list<string>  $components  Component filters (e.g. "country:US", "postal_code:94043").
     */
    public function __construct(
        public string $address = '',
        public string $language = 'en',
        public ?string $region = null,
        public array $components = [],
        public bool $fresh = false,
    ) {}

    public function withAddress(string $address): self
    {
        return new self($address, $this->language, $this->region, $this->components, $this->fresh);
    }

    public function inLanguage(string $language): self
    {
        return new self($this->address, $language, $this->region, $this->components, $this->fresh);
    }

    public function inRegion(string $region): self
    {
        return new self($this->address, $this->language, $region, $this->components, $this->fresh);
    }

    public function filterBy(string ...$components): self
    {
        return new self($this->address, $this->language, $this->region, array_values($components), $this->fresh);
    }

    /**
     * Skip the cache read for this lookup: ask Google, then write the answer back to the
     * cache. The flag never reaches Google and is not part of the cache key.
     */
    public function fresh(bool $fresh = true): self
    {
        return new self($this->address, $this->language, $this->region, $this->components, $fresh);
    }

    /**
     * @return array<string, mixed>
     */
    public function toRequest(): array
    {
        $query = [
            'address' => $this->address,
            'language' => $this->language,
        ];

        if ($this->region !== null) {
            $query['region'] = $this->region;
        }

        if (count($this->components) > 0) {
            $query['components'] = implode('|', $this->components);
        }

        return $query;
    }
}
