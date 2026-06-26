<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class Place
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $photos  Photo resource names (pass to GooglePlaces::photoUrl()).
     * @param  array<string, mixed>  $raw
     * @param  list<AddressComponent>  $addressComponents
     */
    public function __construct(
        public string $name,
        public ?string $id = null,
        public ?string $formattedAddress = null,
        public array $types = [],
        public ?Geometry $geometry = null,
        public ?OpeningHours $openingHours = null,
        public array $photos = [],
        public array $raw = [],
        public array $addressComponents = [],
    ) {}

    /**
     * Map a Places API (New) place resource.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromResponse(array $item): self
    {
        $displayName = (array) ($item['displayName'] ?? []);

        return new self(
            name: (string) ($displayName['text'] ?? ''),
            id: isset($item['id']) ? (string) $item['id'] : null,
            formattedAddress: isset($item['formattedAddress']) ? (string) $item['formattedAddress'] : null,
            types: array_map(strval(...), array_values((array) ($item['types'] ?? []))),
            geometry: isset($item['location'])
                ? Geometry::fromPlace((array) $item['location'], (array) ($item['viewport'] ?? []))
                : null,
            openingHours: isset($item['regularOpeningHours'])
                ? OpeningHours::fromResponse((array) $item['regularOpeningHours'])
                : null,
            photos: array_map(
                static fn (mixed $photo): string => (string) ((array) $photo)['name'],
                array_values((array) ($item['photos'] ?? [])),
            ),
            raw: $item,
            addressComponents: array_map(
                static fn (mixed $component): AddressComponent => AddressComponent::fromPlace((array) $component),
                array_values((array) ($item['addressComponents'] ?? [])),
            ),
        );
    }

    public function isOpenNow(): bool
    {
        return $this->openingHours instanceof OpeningHours && $this->openingHours->isOpen;
    }

    public function coordinates(): ?Location
    {
        return $this->geometry?->location;
    }

    /**
     * The place's primary type, falling back to the first of its types.
     */
    public function primaryType(): ?string
    {
        $primary = $this->raw['primaryType'] ?? null;

        if (is_string($primary) && $primary !== '') {
            return $primary;
        }

        return $this->types[0] ?? null;
    }

    /**
     * Typed accessors (street, city, country, …) over the address components.
     * Populated only when `addressComponents` is included in the field mask.
     */
    public function components(): AddressComponents
    {
        return new AddressComponents($this->addressComponents);
    }

    /**
     * Opening-hour periods that start on the given weekday (0 = Sunday).
     *
     * @return list<OpeningHourPeriod>
     */
    public function openingHoursFor(int $day): array
    {
        if (! $this->openingHours instanceof OpeningHours) {
            return [];
        }

        return array_values(array_filter(
            $this->openingHours->periods,
            static fn (OpeningHourPeriod $period): bool => $period->day === $day,
        ));
    }
}
