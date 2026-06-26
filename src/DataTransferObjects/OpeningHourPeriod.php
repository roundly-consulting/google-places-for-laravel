<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class OpeningHourPeriod
{
    public function __construct(
        public int $day,
        public string $from,
        public string $to,
        public int $openHour = 0,
        public int $openMinute = 0,
        public int $closeHour = 0,
        public int $closeMinute = 0,
    ) {}

    /**
     * Map a Places API (New) `regularOpeningHours.periods[]` entry. The New API
     * gives `open`/`close` as `{day, hour, minute}` integers; `from`/`to` are
     * formatted to `"HHMM"` strings and the raw integers exposed alongside.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromResponse(array $item): self
    {
        $open = (array) ($item['open'] ?? []);
        $close = (array) ($item['close'] ?? []);

        $openHour = (int) ($open['hour'] ?? 0);
        $openMinute = (int) ($open['minute'] ?? 0);
        $closeHour = (int) ($close['hour'] ?? 0);
        $closeMinute = (int) ($close['minute'] ?? 0);

        return new self(
            day: (int) ($open['day'] ?? 0),
            from: sprintf('%02d%02d', $openHour, $openMinute),
            to: sprintf('%02d%02d', $closeHour, $closeMinute),
            openHour: $openHour,
            openMinute: $openMinute,
            closeHour: $closeHour,
            closeMinute: $closeMinute,
        );
    }
}
