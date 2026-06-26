<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
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
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Support\PendingMatrix;
use RoundlyConsulting\GooglePlaces\Support\PendingPhoto;
use RoundlyConsulting\GooglePlaces\Support\PlacesSession;
use RoundlyConsulting\GooglePlaces\Support\SearchPaginator;
use RoundlyConsulting\GooglePlaces\Testing\FakePlacesClient;

/**
 * @method static string photoUrl(string $name, int $maxWidth = 1600, int $maxHeight = 1600)
 * @method static ?Place details(DetailsQuery|string $query)
 * @method static Collection<int, AutocompletePrediction> autocomplete(AutocompleteQuery|string $query)
 * @method static Collection<int, ReverseGeocodingResult> geocode(ReverseGeocodingQuery|Location|float $location, ?float $longitude = null)
 * @method static Collection<int, ReverseGeocodingResult> geocodeAddress(GeocodingQuery|string $query)
 * @method static Collection<int, Place> textSearch(TextSearchQuery|string $query)
 * @method static Collection<int, Place> nearbySearch(NearbySearchQuery $query)
 * @method static SearchPaginator textSearchPaginated(TextSearchQuery|string $query)
 * @method static SearchPaginator nearbySearchPaginated(NearbySearchQuery $query)
 * @method static ?Place findPlace(string $text, ?Location $bias = null)
 * @method static Distance|Roundtrip distance(DistanceQuery $query)
 * @method static DistanceMatrix computeMatrix(MatrixQuery $query)
 *
 * @see PlacesClient
 */
final class GooglePlaces extends Facade
{
    public static function fake(): FakePlacesClient
    {
        $fake = new FakePlacesClient;

        self::swap($fake);

        return $fake;
    }

    /**
     * Start a billing session that ties an autocomplete burst plus its final
     * details() call together under one session token.
     */
    public static function session(?string $token = null): PlacesSession
    {
        return new PlacesSession(self::client(), $token);
    }

    /**
     * Begin a many-origins × many-destinations distance matrix.
     *
     * @param  list<Location>  $origins
     * @param  list<Location>  $destinations
     */
    public static function matrix(array $origins, array $destinations): PendingMatrix
    {
        return new PendingMatrix(self::client(), $origins, $destinations);
    }

    /**
     * Fetch the bytes/URL behind a place photo resource name.
     */
    public static function photo(string $name, int $maxWidth = 1600, int $maxHeight = 1600): PendingPhoto
    {
        return new PendingPhoto($name, $maxWidth, $maxHeight);
    }

    protected static function getFacadeAccessor(): string
    {
        return PlacesClient::class;
    }

    private static function client(): PlacesClient
    {
        // Resolve through the container so a GooglePlaces::fake() swap is honoured.
        return app(PlacesClient::class);
    }
}
