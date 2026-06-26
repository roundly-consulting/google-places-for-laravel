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
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Roundtrip;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Testing\FakePlacesClient;

/**
 * @method static string photoUrl(string $name, int $maxWidth = 1600, int $maxHeight = 1600)
 * @method static ?Place details(DetailsQuery|string $query)
 * @method static Collection<int, AutocompletePrediction> autocomplete(AutocompleteQuery|string $query)
 * @method static Collection<int, ReverseGeocodingResult> geocode(ReverseGeocodingQuery|Location|float $location, ?float $longitude = null)
 * @method static Collection<int, Place> textSearch(TextSearchQuery|string $query)
 * @method static Collection<int, Place> nearbySearch(NearbySearchQuery $query)
 * @method static ?Place findPlace(string $text, ?Location $bias = null)
 * @method static Distance|Roundtrip distance(DistanceQuery $query)
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

    protected static function getFacadeAccessor(): string
    {
        return PlacesClient::class;
    }
}
