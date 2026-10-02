<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Contracts;

use Illuminate\Support\Collection;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ApiCheckResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceMatrix;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MultipleDistances;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Support\PendingMatrix;
use RoundlyConsulting\GooglePlaces\Support\PendingPhoto;
use RoundlyConsulting\GooglePlaces\Support\PlacesSession;
use RoundlyConsulting\GooglePlaces\Support\SearchPaginator;

/**
 * The package's whole API, and the root of the `GooglePlaces` facade. Inject it
 * (or resolve it) to use the client without the facade; `GooglePlaces::fake()`
 * swaps a recording implementation in behind both.
 */
interface PlacesClient
{
    /**
     * Start a billing session that ties an autocomplete burst plus its final
     * details() call together under one session token.
     */
    public function session(?string $token = null): PlacesSession;

    /**
     * Begin a many-origins × many-destinations distance matrix.
     *
     * @param  list<Location>  $origins
     * @param  list<Location>  $destinations
     */
    public function matrix(array $origins, array $destinations): PendingMatrix;

    /**
     * A handle on one place photo: its key-free URL, its bytes, or a copy on a disk.
     */
    public function photo(string $name, int $maxWidth = 1600, int $maxHeight = 1600): PendingPhoto;

    /**
     * The media URL of a photo WITH the API key in its query string — built
     * locally, no request. Server-side use only; hand a browser {@see photoUri()}.
     */
    public function photoUrl(string $name, int $maxWidth = 1600, int $maxHeight = 1600): string;

    /**
     * Resolve Google's key-free, browser-safe URL for a photo (one request).
     *
     * @throws PlacesException
     */
    public function photoUri(string $name, int $maxWidth = 1600, int $maxHeight = 1600): string;

    /**
     * The raw bytes of a photo (one request).
     *
     * @throws PlacesException
     */
    public function photoContents(string $name, int $maxWidth = 1600, int $maxHeight = 1600): string;

    /**
     * Probe the Places, Routes and Geocoding APIs with the configured key and
     * report, per API, whether it is enabled and reachable. The key is redacted
     * from every detail. Probes are neither throttled nor cached.
     *
     * @return list<ApiCheckResult>
     *
     * @throws PlacesException when no API key is configured
     */
    public function check(): array;

    /**
     * @throws PlacesException
     */
    public function details(DetailsQuery|string $query): ?Place;

    /**
     * @return Collection<int, AutocompletePrediction>
     *
     * @throws PlacesException
     */
    public function autocomplete(AutocompleteQuery|string $query): Collection;

    /**
     * @return Collection<int, ReverseGeocodingResult>
     *
     * @throws PlacesException
     */
    public function geocode(ReverseGeocodingQuery|Location|float $location, ?float $longitude = null): Collection;

    /**
     * Forward geocode an address into a collection of geocoding results.
     *
     * @return Collection<int, ReverseGeocodingResult>
     *
     * @throws PlacesException
     */
    public function geocodeAddress(GeocodingQuery|string $query): Collection;

    /**
     * @return Collection<int, Place>
     *
     * @throws PlacesException
     */
    public function textSearch(TextSearchQuery|string $query): Collection;

    /**
     * @return Collection<int, Place>
     *
     * @throws PlacesException
     */
    public function nearbySearch(NearbySearchQuery $query): Collection;

    /**
     * A paginator that transparently follows the text-search `nextPageToken`.
     */
    public function textSearchPaginated(TextSearchQuery|string $query): SearchPaginator;

    /**
     * A paginator over a nearby search (the New API returns a single page).
     */
    public function nearbySearchPaginated(NearbySearchQuery $query): SearchPaginator;

    /**
     * @throws PlacesException
     */
    public function findPlace(string $text, ?Location $bias = null): ?Place;

    /**
     * Distance and travel time from one origin: a `Distance` for one destination, or
     * `MultipleDistances` — one separate trip per destination, not a chained route — for
     * several.
     *
     * @throws PlacesException
     */
    public function distance(DistanceQuery $query): Distance|MultipleDistances;

    /**
     * Compute a full origins × destinations distance matrix.
     *
     * @throws PlacesException
     */
    public function computeMatrix(MatrixQuery $query): DistanceMatrix;
}
