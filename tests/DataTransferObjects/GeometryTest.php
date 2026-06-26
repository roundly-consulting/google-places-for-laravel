<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\Geometry;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;

it('holds values', function () {
    $geo = new Geometry(
        new Location(1, 2),
        new Location(3, 4),
        new Location(5, 6),
    );

    expect($geo)
        ->location->toBeInstanceOf(Location::class)
        ->location->toRequest()->toBe('1,2')
        ->viewportNorthEast->toBeInstanceOf(Location::class)
        ->viewportNorthEast->toRequest()->toBe('3,4')
        ->viewportSouthWest->toBeInstanceOf(Location::class)
        ->viewportSouthWest->toRequest()->toBe('5,6');
});

it('creates instance from google response', function () {
    $geo = Geometry::fromGoogleResponse([
        'location' => ['lat' => 1, 'lng' => 2],
        'viewport' => [
            'northeast' => ['lat' => 3, 'lng' => 4],
            'southwest' => ['lat' => 5, 'lng' => 6],
        ],
    ]);

    expect($geo)
        ->toBeInstanceOf(Geometry::class)
        ->location->toBeInstanceOf(Location::class)
        ->location->toRequest()->toBe('1,2')
        ->viewportNorthEast->toBeInstanceOf(Location::class)
        ->viewportNorthEast->toRequest()->toBe('3,4')
        ->viewportSouthWest->toBeInstanceOf(Location::class)
        ->viewportSouthWest->toRequest()->toBe('5,6');
});
