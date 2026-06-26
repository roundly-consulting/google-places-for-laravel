<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class OpeningHourPeriod
{
    public function __construct(
        public int $day,
        public string $from,
        public string $to,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromGoogleResponse(array $item): self
    {
        $open = (array) $item['open'];
        $close = (array) $item['close'];

        return new self(
            day: (int) $open['day'],
            from: (string) $open['time'],
            to: (string) $close['time'],
        );
    }
}
