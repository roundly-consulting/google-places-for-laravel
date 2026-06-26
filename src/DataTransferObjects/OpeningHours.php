<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class OpeningHours
{
    /**
     * @param  list<OpeningHourPeriod>  $periods
     */
    public function __construct(
        public bool $isOpen,
        public array $periods,
    ) {}

    /**
     * Map a Places API (New) `regularOpeningHours` object.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromResponse(array $item): self
    {
        return new self(
            isOpen: (bool) ($item['openNow'] ?? false),
            periods: array_map(
                static fn (mixed $period): OpeningHourPeriod => OpeningHourPeriod::fromResponse((array) $period),
                array_values((array) ($item['periods'] ?? [])),
            ),
        );
    }
}
