<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class TextSearchQuery
{
    /**
     * @param  list<string>  $priceLevels
     */
    public function __construct(
        public string $textQuery = '',
        public string $language = 'en',
        public ?string $region = null,
        public ?int $pageSize = null,
        public ?string $includedType = null,
        public ?bool $openNow = null,
        public ?float $minRating = null,
        public array $priceLevels = [],
        public LocationDefinition $locationBias = new LocationDefinition,
        public LocationDefinition $locationRestriction = new LocationDefinition,
        public ?string $rankPreference = null,
        public ?string $pageToken = null,
    ) {}

    public function withText(string $textQuery): self
    {
        return $this->copy(textQuery: $textQuery);
    }

    public function inLanguage(string $language): self
    {
        return $this->copy(language: $language);
    }

    public function inRegion(string $region): self
    {
        return $this->copy(region: $region);
    }

    public function take(int $pageSize): self
    {
        return $this->copy(pageSize: $pageSize);
    }

    public function ofType(string $includedType): self
    {
        return $this->copy(includedType: $includedType);
    }

    public function openNow(bool $openNow = true): self
    {
        return $this->copy(openNow: $openNow);
    }

    public function withMinRating(float $minRating): self
    {
        return $this->copy(minRating: $minRating);
    }

    public function withPriceLevels(string ...$priceLevels): self
    {
        return $this->copy(priceLevels: array_values($priceLevels));
    }

    public function preferInArea(LocationDefinition $location): self
    {
        return $this->copy(locationBias: $location);
    }

    public function restrictTo(LocationDefinition $location): self
    {
        return $this->copy(locationRestriction: $location);
    }

    public function rankBy(string $rankPreference): self
    {
        return $this->copy(rankPreference: $rankPreference);
    }

    /**
     * Bias results toward a circle around the given point.
     */
    public function nearby(float $latitude, float $longitude, int $radius = 5000): self
    {
        return $this->preferInArea((new LocationDefinition)->circle(new Location($latitude, $longitude), $radius));
    }

    /**
     * Restrict results to a rectangle between two corners.
     */
    public function withinBounds(Location $low, Location $high): self
    {
        return $this->restrictTo((new LocationDefinition)->rectangle($low, $high));
    }

    public function withPageToken(string $pageToken): self
    {
        return $this->copy(pageToken: $pageToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function toBody(): array
    {
        $body = [
            'textQuery' => $this->textQuery,
            'languageCode' => $this->language,
        ];

        if ($this->region !== null) {
            $body['regionCode'] = $this->region;
        }

        if ($this->pageSize !== null) {
            $body['pageSize'] = $this->pageSize;
        }

        if ($this->includedType !== null) {
            $body['includedType'] = $this->includedType;
        }

        if ($this->openNow !== null) {
            $body['openNow'] = $this->openNow;
        }

        if ($this->minRating !== null) {
            $body['minRating'] = $this->minRating;
        }

        if (count($this->priceLevels) > 0) {
            $body['priceLevels'] = $this->priceLevels;
        }

        if ($this->locationBias->value !== null) {
            $body['locationBias'] = $this->locationBias->value;
        }

        if ($this->locationRestriction->value !== null) {
            $body['locationRestriction'] = $this->locationRestriction->value;
        }

        if ($this->rankPreference !== null) {
            $body['rankPreference'] = $this->rankPreference;
        }

        if ($this->pageToken !== null) {
            $body['pageToken'] = $this->pageToken;
        }

        return $body;
    }

    /**
     * @param  list<string>|null  $priceLevels
     */
    private function copy(
        ?string $textQuery = null,
        ?string $language = null,
        ?string $region = null,
        ?int $pageSize = null,
        ?string $includedType = null,
        ?bool $openNow = null,
        ?float $minRating = null,
        ?array $priceLevels = null,
        ?LocationDefinition $locationBias = null,
        ?LocationDefinition $locationRestriction = null,
        ?string $rankPreference = null,
        ?string $pageToken = null,
    ): self {
        return new self(
            textQuery: $textQuery ?? $this->textQuery,
            language: $language ?? $this->language,
            region: $region ?? $this->region,
            pageSize: $pageSize ?? $this->pageSize,
            includedType: $includedType ?? $this->includedType,
            openNow: $openNow ?? $this->openNow,
            minRating: $minRating ?? $this->minRating,
            priceLevels: $priceLevels ?? $this->priceLevels,
            locationBias: $locationBias ?? $this->locationBias,
            locationRestriction: $locationRestriction ?? $this->locationRestriction,
            rankPreference: $rankPreference ?? $this->rankPreference,
            pageToken: $pageToken ?? $this->pageToken,
        );
    }
}
