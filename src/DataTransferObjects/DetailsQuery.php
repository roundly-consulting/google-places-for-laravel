<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class DetailsQuery
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        public string $place,
        public array $fields = ['address_components', 'formatted_address', 'name', 'geometry', 'type', 'photo'],
        public string $language = 'en',
        public ?string $sessionToken = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toRequest(): array
    {
        $query = [
            'place_id' => $this->place,
            'fields' => implode(',', $this->fields),
            'language' => $this->language,
        ];

        if ($this->sessionToken !== null) {
            $query['sessiontoken'] = $this->sessionToken;
        }

        return $query;
    }
}
