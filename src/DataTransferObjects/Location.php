<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class Location
{
    public function __construct(public float $latitude, public float $longitude) {}

    public function toRequest(): string
    {
        return $this->latitude.','.$this->longitude;
    }
}
