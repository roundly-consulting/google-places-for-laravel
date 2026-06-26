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
        public array $types = [],
        public ?string $mainText = null,
        public ?string $secondaryText = null,
        public array $raw = [],
    ) {}

    /**
     * Map a Places API (New) `suggestions[].placePrediction` object.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromResponse(array $item): self
    {
        $text = (array) ($item['text'] ?? []);
        $structured = (array) ($item['structuredFormat'] ?? []);
        $mainText = (array) ($structured['mainText'] ?? []);
        $secondaryText = (array) ($structured['secondaryText'] ?? []);

        return new self(
            description: (string) ($text['text'] ?? ''),
            placeId: (string) ($item['placeId'] ?? ''),
            types: array_map(strval(...), array_values((array) ($item['types'] ?? []))),
            mainText: isset($mainText['text']) ? (string) $mainText['text'] : null,
            secondaryText: isset($secondaryText['text']) ? (string) $secondaryText['text'] : null,
            raw: $item,
        );
    }
}
