<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class DetailsQuery
{
    /**
     * @param  list<string>|null  $fields  Field-mask override (Places API New field names).
     *                                     Null uses the configured default mask.
     */
    public function __construct(
        public string $place,
        public ?array $fields = null,
        public string $language = 'en',
        public ?string $sessionToken = null,
    ) {}

    /**
     * The `X-Goog-FieldMask` value, or null to fall back to the configured default.
     */
    public function fieldMask(): ?string
    {
        return $this->fields === null ? null : implode(',', $this->fields);
    }

    /**
     * Query parameters for `GET /v1/places/{placeId}`.
     *
     * @return array<string, mixed>
     */
    public function toRequest(): array
    {
        $query = [
            'languageCode' => $this->language,
        ];

        if ($this->sessionToken !== null) {
            $query['sessionToken'] = $this->sessionToken;
        }

        return $query;
    }
}
