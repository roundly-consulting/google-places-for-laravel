<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Contracts;

use Illuminate\Support\Collection;
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
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

interface PlacesClient
{
    public function photoUrl(string $name, int $maxWidth = 1600, int $maxHeight = 1600): string;

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
     * @throws PlacesException
     */
    public function findPlace(string $text, ?Location $bias = null): ?Place;

    /**
     * @throws PlacesException
     */
    public function distance(DistanceQuery $query): Distance|Roundtrip;
}
