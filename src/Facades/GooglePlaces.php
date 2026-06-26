<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Roundtrip;
use RoundlyConsulting\GooglePlaces\Places;

/**
 * @method static string photoUrl(string $photo, int $maxWidth = 1600, int $maxHeight = 1600)
 * @method static ?Place details(DetailsQuery $query)
 * @method static Collection<int, AutocompletePrediction> autocomplete(AutocompleteQuery $query)
 * @method static Collection<int, ReverseGeocodingResult> geocode(ReverseGeocodingQuery $query)
 * @method static Distance|Roundtrip distance(DistanceQuery $query)
 *
 * @see Places
 */
final class GooglePlaces extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Places::class;
    }
}
