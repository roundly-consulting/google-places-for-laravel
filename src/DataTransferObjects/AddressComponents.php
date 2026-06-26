<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

/**
 * Ergonomic, typed accessors over a flat list of address components.
 */
final readonly class AddressComponents
{
    /**
     * @param  list<AddressComponent>  $components
     */
    public function __construct(
        public array $components = [],
    ) {}

    public function streetNumber(): ?string
    {
        return $this->long('street_number');
    }

    public function street(): ?string
    {
        return $this->long('route');
    }

    public function city(): ?string
    {
        return $this->long('locality')
            ?? $this->long('postal_town')
            ?? $this->long('sublocality')
            ?? $this->long('sublocality_level_1');
    }

    public function postalCode(): ?string
    {
        return $this->long('postal_code');
    }

    public function state(): ?string
    {
        return $this->long('administrative_area_level_1');
    }

    public function country(): ?string
    {
        return $this->long('country');
    }

    public function countryCode(): ?string
    {
        return $this->short('country');
    }

    public function has(string $type): bool
    {
        return $this->first($type) instanceof AddressComponent;
    }

    public function first(string $type): ?AddressComponent
    {
        foreach ($this->components as $component) {
            if (in_array($type, $component->types, true)) {
                return $component;
            }
        }

        return null;
    }

    private function long(string $type): ?string
    {
        return $this->first($type)?->longName;
    }

    private function short(string $type): ?string
    {
        return $this->first($type)?->shortName;
    }
}
