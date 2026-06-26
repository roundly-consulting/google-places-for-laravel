<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final class LocationDefinition
{
    public function __construct(
        public ?string $value = null
    ) {}

    public function circular(Location $location, int $radius = 5000): self
    {
        $this->value = 'circle:'.$radius.'@'.$location->latitude.','.$location->longitude;

        return $this;
    }

    public function rectangular(float $south, float $west, float $north, float $east): self
    {
        $this->value = 'rectangle:'.$south.','.$west.'|'.$north.','.$east;

        return $this;
    }
}
