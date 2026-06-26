<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;

it('builds the geocoding query with optional type filters', function () {
    $query = new ReverseGeocodingQuery(
        location: new Location(1, 2),
        resultTypes: ['street_address'],
        locationTypes: ['ROOFTOP'],
        language: 'sk',
    );

    expect($query->toRequest())->toBe([
        'latlng' => '1,2',
        'language' => 'sk',
        'result_type' => 'street_address',
        'location_type' => 'ROOFTOP',
    ]);
});

it('omits type filters when none are given', function () {
    $query = new ReverseGeocodingQuery(new Location(1, 2));

    expect($query->toRequest())->toBe(['latlng' => '1,2', 'language' => 'en']);
});
