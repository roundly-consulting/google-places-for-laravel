<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\Geometry;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\OpeningHourPeriod;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\OpeningHours;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;

it('holds values', function () {
    $place = new Place(
        'Somewhere',
        ['home'],
        new Geometry(
            new Location(1, 2),
            new Location(3, 4),
            new Location(5, 6),
        ),
        new OpeningHours(true, [1]),
    );

    expect($place)
        ->name->toBe('Somewhere')
        ->types->toBe(['home'])
        ->geometry->location->toBeInstanceOf(Location::class)
        ->geometry->location->toRequest()->toBe('1,2')
        ->geometry->viewportNorthEast->toBeInstanceOf(Location::class)
        ->geometry->viewportNorthEast->toRequest()->toBe('3,4')
        ->geometry->viewportSouthWest->toBeInstanceOf(Location::class)
        ->geometry->viewportSouthWest->toRequest()->toBe('5,6')
        ->openingHours->isOpen->toBeTrue()
        ->openingHours->periods->toBe([1]);
});

it('creates instance from google response', function () {
    $place = Place::fromGoogleResponse([
        'name' => 'Somewhere',
        'types' => ['home'],
        'geometry' => [
            'location' => ['lat' => 1, 'lng' => 2],
            'viewport' => [
                'northeast' => ['lat' => 3, 'lng' => 4],
                'southwest' => ['lat' => 5, 'lng' => 6],
            ],
        ],
        'opening_hours' => [
            'open_now' => true,
            'periods' => [
                [
                    'open' => ['day' => 1, 'time' => '10:00'],
                    'close' => ['day' => 1, 'time' => '17:00'],
                ],
            ],
        ],
    ]);

    expect($place)
        ->toBeInstanceOf(Place::class)
        ->name->toBe('Somewhere')
        ->types->toBe(['home'])
        ->geometry->location->toBeInstanceOf(Location::class)
        ->geometry->location->toRequest()->toBe('1,2')
        ->geometry->viewportNorthEast->toBeInstanceOf(Location::class)
        ->geometry->viewportNorthEast->toRequest()->toBe('3,4')
        ->geometry->viewportSouthWest->toBeInstanceOf(Location::class)
        ->geometry->viewportSouthWest->toRequest()->toBe('5,6')
        ->openingHours->isOpen->toBeTrue()
        ->openingHours->periods->toHaveCount(1)
        ->and($place->openingHours->periods[0])
        ->toBeInstanceOf(OpeningHourPeriod::class)
        ->day->toBe(1)
        ->from->toBe('10:00')
        ->to->toBe('17:00');
});
