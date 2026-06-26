<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Testing;

use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceMatrix;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Roundtrip;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\SearchPage;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;
use RoundlyConsulting\GooglePlaces\Support\SearchPaginator;

final class FakePlacesClient implements PlacesClient
{
    /** @var list<AutocompletePrediction> */
    private array $autocompleteReturn = [];

    private ?Place $detailsReturn = null;

    /** @var list<ReverseGeocodingResult> */
    private array $geocodeReturn = [];

    /** @var list<ReverseGeocodingResult> */
    private array $geocodeAddressReturn = [];

    private ?DistanceMatrix $matrixReturn = null;

    /** @var list<Place> */
    private array $textSearchReturn = [];

    /** @var list<Place> */
    private array $nearbySearchReturn = [];

    private ?Place $findPlaceReturn = null;

    private Distance|Roundtrip|null $distanceReturn = null;

    private ?string $photoUrlReturn = null;

    /** @var list<AutocompleteQuery> */
    private array $autocompleteCalls = [];

    /** @var list<DetailsQuery> */
    private array $detailsCalls = [];

    /** @var list<ReverseGeocodingQuery> */
    private array $geocodeCalls = [];

    /** @var list<GeocodingQuery> */
    private array $geocodeAddressCalls = [];

    /** @var list<MatrixQuery> */
    private array $matrixCalls = [];

    /** @var list<TextSearchQuery> */
    private array $textSearchCalls = [];

    /** @var list<NearbySearchQuery> */
    private array $nearbySearchCalls = [];

    /** @var list<string> */
    private array $findPlaceCalls = [];

    /** @var list<DistanceQuery> */
    private array $distanceCalls = [];

    /**
     * @param  list<AutocompletePrediction>  $predictions
     */
    public function withAutocomplete(array $predictions): self
    {
        $this->autocompleteReturn = $predictions;

        return $this;
    }

    public function withDetails(?Place $place): self
    {
        $this->detailsReturn = $place;

        return $this;
    }

    /**
     * @param  list<ReverseGeocodingResult>  $results
     */
    public function withGeocode(array $results): self
    {
        $this->geocodeReturn = $results;

        return $this;
    }

    /**
     * @param  list<ReverseGeocodingResult>  $results
     */
    public function withGeocodeAddress(array $results): self
    {
        $this->geocodeAddressReturn = $results;

        return $this;
    }

    public function withMatrix(DistanceMatrix $matrix): self
    {
        $this->matrixReturn = $matrix;

        return $this;
    }

    /**
     * @param  list<Place>  $places
     */
    public function withTextSearch(array $places): self
    {
        $this->textSearchReturn = $places;

        return $this;
    }

    /**
     * @param  list<Place>  $places
     */
    public function withNearbySearch(array $places): self
    {
        $this->nearbySearchReturn = $places;

        return $this;
    }

    public function withFindPlace(?Place $place): self
    {
        $this->findPlaceReturn = $place;

        return $this;
    }

    public function withDistance(Distance|Roundtrip $distance): self
    {
        $this->distanceReturn = $distance;

        return $this;
    }

    public function withPhotoUrl(string $url): self
    {
        $this->photoUrlReturn = $url;

        return $this;
    }

    public function photoUrl(string $name, int $maxWidth = 1600, int $maxHeight = 1600): string
    {
        return $this->photoUrlReturn ?? "https://places.googleapis.com/v1/{$name}/media?maxWidthPx={$maxWidth}&maxHeightPx={$maxHeight}";
    }

    public function details(DetailsQuery|string $query): ?Place
    {
        $this->detailsCalls[] = is_string($query) ? new DetailsQuery($query) : $query;

        return $this->detailsReturn;
    }

    public function autocomplete(AutocompleteQuery|string $query): Collection
    {
        $this->autocompleteCalls[] = is_string($query) ? new AutocompleteQuery($query) : $query;

        return collect($this->autocompleteReturn);
    }

    public function geocode(ReverseGeocodingQuery|Location|float $location, ?float $longitude = null): Collection
    {
        $this->geocodeCalls[] = match (true) {
            $location instanceof ReverseGeocodingQuery => $location,
            $location instanceof Location => new ReverseGeocodingQuery($location),
            default => new ReverseGeocodingQuery(new Location($location, (float) $longitude)),
        };

        return collect($this->geocodeReturn);
    }

    public function geocodeAddress(GeocodingQuery|string $query): Collection
    {
        $this->geocodeAddressCalls[] = is_string($query) ? new GeocodingQuery($query) : $query;

        return collect($this->geocodeAddressReturn);
    }

    public function textSearch(TextSearchQuery|string $query): Collection
    {
        $this->textSearchCalls[] = is_string($query) ? new TextSearchQuery($query) : $query;

        return collect($this->textSearchReturn);
    }

    public function nearbySearch(NearbySearchQuery $query): Collection
    {
        $this->nearbySearchCalls[] = $query;

        return collect($this->nearbySearchReturn);
    }

    public function textSearchPaginated(TextSearchQuery|string $query): SearchPaginator
    {
        $this->textSearchCalls[] = is_string($query) ? new TextSearchQuery($query) : $query;

        return $this->paginatorFor($this->textSearchReturn);
    }

    public function nearbySearchPaginated(NearbySearchQuery $query): SearchPaginator
    {
        $this->nearbySearchCalls[] = $query;

        return $this->paginatorFor($this->nearbySearchReturn);
    }

    public function computeMatrix(MatrixQuery $query): DistanceMatrix
    {
        $this->matrixCalls[] = $query;

        return $this->matrixReturn ?? new DistanceMatrix([], count($query->origins), count($query->destinations), TravelMode::Driving);
    }

    public function findPlace(string $text, ?Location $bias = null): ?Place
    {
        $this->findPlaceCalls[] = $text;

        return $this->findPlaceReturn;
    }

    public function distance(DistanceQuery $query): Distance|Roundtrip
    {
        $this->distanceCalls[] = $query;

        return $this->distanceReturn ?? new Distance('0 km', 0, '0s', 0);
    }

    /**
     * @param  (Closure(AutocompleteQuery): bool)|null  $callback
     */
    public function assertAutocompleted(?Closure $callback = null): void
    {
        $this->assertCalled('autocomplete', $this->autocompleteCalls, $callback);
    }

    /**
     * @param  (Closure(DetailsQuery): bool)|string|null  $callback
     */
    public function assertDetailsRequested(Closure|string|null $callback = null): void
    {
        if (is_string($callback)) {
            $placeId = $callback;
            $callback = static fn (DetailsQuery $query): bool => $query->place === $placeId;
        }

        $this->assertCalled('details', $this->detailsCalls, $callback);
    }

    /**
     * @param  (Closure(ReverseGeocodingQuery): bool)|null  $callback
     */
    public function assertGeocoded(?Closure $callback = null): void
    {
        $this->assertCalled('geocode', $this->geocodeCalls, $callback);
    }

    /**
     * @param  (Closure(GeocodingQuery): bool)|string|null  $callback
     */
    public function assertAddressGeocoded(Closure|string|null $callback = null): void
    {
        if (is_string($callback)) {
            $address = $callback;
            $callback = static fn (GeocodingQuery $query): bool => $query->address === $address;
        }

        $this->assertCalled('geocodeAddress', $this->geocodeAddressCalls, $callback);
    }

    /**
     * @param  (Closure(MatrixQuery): bool)|null  $callback
     */
    public function assertMatrixComputed(?Closure $callback = null): void
    {
        $this->assertCalled('computeMatrix', $this->matrixCalls, $callback);
    }

    /**
     * @param  (Closure(TextSearchQuery): bool)|null  $callback
     */
    public function assertTextSearched(?Closure $callback = null): void
    {
        $this->assertCalled('textSearch', $this->textSearchCalls, $callback);
    }

    /**
     * @param  (Closure(NearbySearchQuery): bool)|null  $callback
     */
    public function assertNearbySearched(?Closure $callback = null): void
    {
        $this->assertCalled('nearbySearch', $this->nearbySearchCalls, $callback);
    }

    /**
     * @param  (Closure(string): bool)|null  $callback
     */
    public function assertFindPlaceRequested(?Closure $callback = null): void
    {
        $this->assertCalled('findPlace', $this->findPlaceCalls, $callback);
    }

    /**
     * @param  (Closure(DistanceQuery): bool)|null  $callback
     */
    public function assertDistanceRequested(?Closure $callback = null): void
    {
        $this->assertCalled('distance', $this->distanceCalls, $callback);
    }

    public function assertNothingAutocompleted(): void
    {
        Assert::assertCount(0, $this->autocompleteCalls, 'Expected no autocomplete calls.');
    }

    public function assertNothingGeocoded(): void
    {
        Assert::assertCount(0, $this->geocodeCalls, 'Expected no geocode calls.');
    }

    public function assertNothingRequested(): void
    {
        Assert::assertSame(
            0,
            count($this->autocompleteCalls)
                + count($this->detailsCalls)
                + count($this->geocodeCalls)
                + count($this->geocodeAddressCalls)
                + count($this->matrixCalls)
                + count($this->textSearchCalls)
                + count($this->nearbySearchCalls)
                + count($this->findPlaceCalls)
                + count($this->distanceCalls),
            'Expected no Google Places calls.',
        );
    }

    /**
     * @param  list<Place>  $places
     */
    private function paginatorFor(array $places): SearchPaginator
    {
        return new SearchPaginator(
            static fn (?string $pageToken): SearchPage => new SearchPage($places),
            1,
        );
    }

    /**
     * @param  list<mixed>  $calls
     * @param  (Closure(mixed): bool)|null  $callback
     */
    private function assertCalled(string $name, array $calls, ?Closure $callback): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($calls, "Expected a {$name} call, but none were made.");

            return;
        }

        $matched = false;

        foreach ($calls as $call) {
            if ($callback($call) === true) {
                $matched = true;

                break;
            }
        }

        Assert::assertTrue($matched, "Expected a {$name} call matching the callback, but none were made.");
    }
}
