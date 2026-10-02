<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MultipleDistances;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MultipleLocations;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

it('holds a location and serialises it', function () {
    expect(new Location(12.34, 56.78))
        ->latitude->toBe(12.34)
        ->longitude->toBe(56.78)
        ->toRequest()->toBe('12.34,56.78');
});

it('maps multiple locations', function () {
    $locations = new MultipleLocations([new Location(1, 2), new Location(3, 4)]);

    expect($locations->map(fn (Location $l): string => $l->toRequest())->all())->toBe(['1,2', '3,4']);
});

it('builds a circular location definition', function () {
    $definition = (new LocationDefinition)->circle(new Location(1, 2), 1000);

    expect($definition->value)->toBe([
        'circle' => [
            'center' => ['latitude' => 1.0, 'longitude' => 2.0],
            'radius' => 1000,
        ],
    ]);
});

it('builds a rectangular location definition', function () {
    $definition = (new LocationDefinition)->rectangle(new Location(1, 2), new Location(3, 4));

    expect($definition->value)->toBe([
        'rectangle' => [
            'low' => ['latitude' => 1.0, 'longitude' => 2.0],
            'high' => ['latitude' => 3.0, 'longitude' => 4.0],
        ],
    ]);

    expect((new LocationDefinition)->value)->toBeNull();
});

it('maps a single routes element to a distance', function () {
    $distance = Distance::fromRoutesElement([
        'distanceMeters' => 10000,
        'duration' => '600s',
    ], TravelMode::Walking);

    expect($distance)
        ->distanceInMeters->toBe(10000)
        ->humanReadableDistance->toBe('10 km')
        ->durationInSeconds->toBe(600)
        ->humanReadableDuration->toBe('10m')
        ->type->toBe(TravelMode::Walking)
        ->and($distance->isWalking())->toBeTrue()
        ->and($distance->isDriving())->toBeFalse();
});

it('normalises a travel-mode string on a distance', function () {
    expect((new Distance('1 km', 1000, '1m', 60, 'driving'))->isDriving())->toBeTrue();
});

it('maps several routes elements to one distance per destination, never a sum', function () {
    $distances = MultipleDistances::fromRoutesElements([
        ['distanceMeters' => 10000, 'duration' => '600s'],
        ['distanceMeters' => 15000, 'duration' => '900s'],
    ], TravelMode::Walking);

    expect($distances)
        ->toBeInstanceOf(MultipleDistances::class)
        ->type->toBe(TravelMode::Walking)
        ->and($distances->distances)->toHaveCount(2)
        ->and($distances->distances[0]->distanceInMeters)->toBe(10000)
        ->and($distances->distances[1]->humanReadableDuration)->toBe('15m')
        ->and($distances->distances[1]->type)->toBe(TravelMode::Walking)
        // One origin to several destinations is not one route: there is no total to add up.
        ->and(property_exists($distances, 'distanceInMeters'))->toBeFalse()
        ->and(property_exists($distances, 'durationInSeconds'))->toBeFalse();
});

it('converts meters to a human-readable string', function () {
    expect(Distance::metersToHuman(1450))->toBe('1.5 km');
});

it('holds a place value object', function () {
    $place = new Place(name: 'Somewhere', id: 'p1', types: ['home']);

    expect($place)->name->toBe('Somewhere')->id->toBe('p1')->types->toBe(['home']);
});
