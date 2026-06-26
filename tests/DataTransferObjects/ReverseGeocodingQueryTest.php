<?php

use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;

it('holds values', function () {
    $q = new ReverseGeocodingQuery(
        location: new Location(1, 2),
        resultTypes: ['one', 'two'],
        locationTypes: ['yep', 'nop'],
        language: 'sk',
    );

    expect($q)
        ->location->toBeInstanceOf(Location::class)
        ->location->toRequest()->toBe('1,2')
        ->resultTypes->toBe(['one', 'two'])
        ->locationTypes->toBe(['yep', 'nop'])
        ->language->toBe('sk');
});

it('returns array to request', function () {
    $q = new ReverseGeocodingQuery(
        location: new Location(1, 2),
        resultTypes: ['one', 'two'],
        locationTypes: ['yep', 'nop'],
        language: 'sk',
    );

    expect($q->toRequest())
        ->toBe([
            'latlng' => '1,2',
            'language' => 'sk',
            'result_type' => 'one|two',
            'location_type' => 'yep|nop',
        ]);
});
