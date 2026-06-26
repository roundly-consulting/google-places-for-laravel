<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class AutocompletePrediction
{
    /**
     * @param  list<string>  $types
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $description,
        public string $placeId,
        public string $reference,
        public array $types,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromGoogleResponse(array $item): self
    {
        return new self(
            description: (string) $item['description'],
            placeId: (string) $item['place_id'],
            reference: (string) $item['reference'],
            types: array_map(strval(...), array_values((array) $item['types'])),
            raw: $item,
        );
    }
}
