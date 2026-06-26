<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

/**
 * One origin × destination pair of a distance matrix.
 */
final readonly class MatrixElement
{
    public function __construct(
        public int $originIndex,
        public int $destinationIndex,
        public string $condition,
        public ?Distance $distance = null,
        public ?string $status = null,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromRoutesElement(array $item, TravelMode $type = TravelMode::Driving): self
    {
        $condition = is_string($item['condition'] ?? null) ? (string) $item['condition'] : 'ROUTE_NOT_FOUND';
        $statusCode = (array) ($item['status'] ?? []);
        $status = isset($statusCode['code']) ? (string) $statusCode['code'] : null;

        return new self(
            originIndex: (int) ($item['originIndex'] ?? 0),
            destinationIndex: (int) ($item['destinationIndex'] ?? 0),
            condition: $condition,
            distance: $condition === 'ROUTE_EXISTS' ? Distance::fromRoutesElement($item, $type) : null,
            status: $status,
        );
    }

    public function hasRoute(): bool
    {
        return $this->condition === 'ROUTE_EXISTS' && $this->distance instanceof Distance;
    }
}
