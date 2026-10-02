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

it('renders coordinates as plain decimals, never in scientific notation', function (float $lat, float $lng, string $expected) {
    expect((new Location($lat, $lng))->toRequest())->toBe($expected)
        ->and((new ReverseGeocodingQuery(new Location($lat, $lng)))->toRequest()['latlng'])->toBe($expected);
})->with([
    'equator band' => [0.00001, 17.0, '0.00001,17'],
    'greenwich band' => [48.1486, -0.000012345, '48.1486,-0.000012345'],
    'negative zero' => [-0.0, 0.0, '0,0'],
    'ordinary' => [48.1486, 17.1077, '48.1486,17.1077'],
    'tiny' => [1.0E-9, -1.0E-9, '0.000000001,-0.000000001'],
]);
