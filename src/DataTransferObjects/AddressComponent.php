<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class AddressComponent
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public string $longName,
        public string $shortName,
        public array $types,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromGoogleResponse(array $item): self
    {
        return new self(
            longName: (string) $item['long_name'],
            shortName: (string) $item['short_name'],
            types: array_map(strval(...), array_values((array) $item['types'])),
        );
    }
}
