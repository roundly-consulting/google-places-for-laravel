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
     * @param  array<string, mixed>  $item
     */
    public static function fromGoogleResponse(array $item): self
    {
        return new self(
            isOpen: (bool) $item['open_now'],
            periods: array_map(
                static fn (mixed $period): OpeningHourPeriod => OpeningHourPeriod::fromGoogleResponse((array) $period),
                array_values((array) $item['periods']),
            ),
        );
    }
}
