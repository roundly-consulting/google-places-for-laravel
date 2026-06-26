<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

use Closure;
use Illuminate\Support\Collection;

final readonly class MultipleLocations
{
    /**
     * @param  list<Location>  $locations
     */
    public function __construct(public array $locations = []) {}

    public function toRequest(): string
    {
        return $this->map(static fn (Location $location): string => $location->toRequest())
            ->implode('|');
    }

    /**
     * @param  Closure(Location): mixed  $callback
     * @return Collection<int, mixed>
     */
    public function map(Closure $callback): Collection
    {
        return collect($this->locations)->map($callback);
    }
}
