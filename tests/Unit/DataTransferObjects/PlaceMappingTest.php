<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\AddressComponent;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Geometry;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\OpeningHourPeriod;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\OpeningHours;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;

it('maps a Places API (New) place resource', function () {
    $place = Place::fromResponse(placeResponse());

    expect($place)
        ->name->toBe('Somewhere')
        ->id->toBe('place-1')
        ->formattedAddress->toBe('123 Main St')
        ->types->toBe(['home'])
        ->photos->toBe(['places/place-1/photos/abc'])
        ->geometry->location->toRequest()->toBe('1,2')
        ->geometry->viewportNorthEast->toRequest()->toBe('3,4')
        ->geometry->viewportSouthWest->toRequest()->toBe('5,6')
        ->openingHours->isOpen->toBeTrue();
});

it('maps a minimal place with no geometry or hours', function () {
    $place = Place::fromResponse(['displayName' => ['text' => 'Bare']]);

    expect($place)
        ->name->toBe('Bare')
        ->id->toBeNull()
        ->geometry->toBeNull()
        ->openingHours->toBeNull()
        ->photos->toBe([]);
});

it('falls back to the point when a place has no viewport', function () {
    $geometry = Geometry::fromPlace(['latitude' => 1, 'longitude' => 2]);

    expect($geometry)
        ->location->toRequest()->toBe('1,2')
        ->viewportNorthEast->toRequest()->toBe('1,2')
        ->viewportSouthWest->toRequest()->toBe('1,2');
});

it('formats new opening-hour periods to HHMM strings with integer accessors', function () {
    $period = OpeningHourPeriod::fromResponse([
        'open' => ['day' => 2, 'hour' => 8, 'minute' => 5],
        'close' => ['day' => 2, 'hour' => 22, 'minute' => 0],
    ]);

    expect($period)
        ->day->toBe(2)
        ->from->toBe('0805')
        ->to->toBe('2200')
        ->openHour->toBe(8)
        ->openMinute->toBe(5)
        ->closeHour->toBe(22)
        ->closeMinute->toBe(0);
});

it('defaults openNow to false when absent', function () {
    expect(OpeningHours::fromResponse([])->isOpen)->toBeFalse();
});

it('maps a new autocomplete place prediction', function () {
    $prediction = AutocompletePrediction::fromResponse([
        'placeId' => 'p1',
        'text' => ['text' => 'Café Roma'],
        'structuredFormat' => [
            'mainText' => ['text' => 'Café Roma'],
            'secondaryText' => ['text' => 'Bratislava'],
        ],
        'types' => ['cafe'],
    ]);

    expect($prediction)
        ->description->toBe('Café Roma')
        ->placeId->toBe('p1')
        ->types->toBe(['cafe'])
        ->mainText->toBe('Café Roma')
        ->secondaryText->toBe('Bratislava');
});

it('maps an autocomplete prediction without structured format', function () {
    $prediction = AutocompletePrediction::fromResponse([
        'placeId' => 'p2',
        'text' => ['text' => 'Plain'],
    ]);

    expect($prediction)
        ->mainText->toBeNull()
        ->secondaryText->toBeNull()
        ->types->toBe([]);
});

it('maps the geocoding api geometry shape', function () {
    $geometry = Geometry::fromResponse([
        'location' => ['lat' => 1, 'lng' => 2],
        'viewport' => ['northeast' => ['lat' => 3, 'lng' => 4], 'southwest' => ['lat' => 5, 'lng' => 6]],
    ]);

    expect($geometry)
        ->location->toRequest()->toBe('1,2')
        ->viewportNorthEast->toRequest()->toBe('3,4');
});

it('maps an address component', function () {
    $component = AddressComponent::fromResponse([
        'long_name' => 'Main Street',
        'short_name' => 'Main St',
        'types' => ['route'],
    ]);

    expect($component)->longName->toBe('Main Street')->shortName->toBe('Main St')->types->toBe(['route']);
});

it('maps a reverse geocoding result', function () {
    $result = ReverseGeocodingResult::fromResponse([
        'formatted_address' => 'Somewhere',
        'place_id' => 'g1',
        'geometry' => [
            'location' => ['lat' => 1, 'lng' => 2],
            'viewport' => ['northeast' => ['lat' => 3, 'lng' => 4], 'southwest' => ['lat' => 5, 'lng' => 6]],
        ],
        'types' => ['street_address'],
        'address_components' => [
            ['long_name' => 'Main Street', 'short_name' => 'Main St', 'types' => ['route']],
        ],
    ]);

    expect($result)
        ->address->toBe('Somewhere')
        ->placeId->toBe('g1')
        ->and($result->components[0])->toBeInstanceOf(AddressComponent::class)
        ->and($result->geometry->location)->toBeInstanceOf(Location::class);
});
