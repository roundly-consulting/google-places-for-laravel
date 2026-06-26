<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\AddressComponent;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Geometry;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;

it('holds values', function () {
    $result = new ReverseGeocodingResult(
        address: 'Somewhere',
        placeId: 'f07575ec-4fcc-4b9e-a5f4-1faa7d59f7f3',
        geometry: Geometry::fromGoogleResponse([
            'location' => ['lat' => 1, 'lng' => 2],
            'viewport' => [
                'northeast' => ['lat' => 3, 'lng' => 4],
                'southwest' => ['lat' => 5, 'lng' => 6],
            ],
        ]),
        types: ['one', 'two'],
        components: [
            new AddressComponent(
                'Very long name',
                'Short name',
                ['one', 'two'],
            ),
        ],
    );

    expect($result)
        ->address->toBe('Somewhere')
        ->placeId->toBe('f07575ec-4fcc-4b9e-a5f4-1faa7d59f7f3')
        ->types->toBe(['one', 'two'])
        ->and($result->geometry)
        ->toBeInstanceOf(Geometry::class)
        ->location->toBeInstanceOf(Location::class)
        ->location->toRequest()->toBe('1,2')
        ->viewportNorthEast->toBeInstanceOf(Location::class)
        ->viewportNorthEast->toRequest()->toBe('3,4')
        ->viewportSouthWest->toBeInstanceOf(Location::class)
        ->viewportSouthWest->toRequest()->toBe('5,6')
        ->and($result->components)
        ->toHaveCount(1)
        ->and($result->components[0])
        ->toBeInstanceOf(AddressComponent::class)
        ->longName->toBe('Very long name')
        ->shortName->toBe('Short name')
        ->types->toBe(['one', 'two']);
});

it('creates instance from google response', function () {
    $result = ReverseGeocodingResult::fromGoogleResponse([
        'formatted_address' => 'Somewhere',
        'place_id' => 'f07575ec-4fcc-4b9e-a5f4-1faa7d59f7f3',
        'geometry' => [
            'location' => ['lat' => 1, 'lng' => 2],
            'viewport' => [
                'northeast' => ['lat' => 3, 'lng' => 4],
                'southwest' => ['lat' => 5, 'lng' => 6],
            ],
        ],
        'types' => ['one', 'two'],
        'address_components' => [
            [
                'long_name' => 'Very long name',
                'short_name' => 'Short name',
                'types' => ['one', 'two'],
            ],
        ],
    ]);

    expect($result)
        ->address->toBe('Somewhere')
        ->placeId->toBe('f07575ec-4fcc-4b9e-a5f4-1faa7d59f7f3')
        ->types->toBe(['one', 'two'])
        ->and($result->geometry)
        ->toBeInstanceOf(Geometry::class)
        ->location->toBeInstanceOf(Location::class)
        ->location->toRequest()->toBe('1,2')
        ->viewportNorthEast->toBeInstanceOf(Location::class)
        ->viewportNorthEast->toRequest()->toBe('3,4')
        ->viewportSouthWest->toBeInstanceOf(Location::class)
        ->viewportSouthWest->toRequest()->toBe('5,6')
        ->and($result->components)
        ->toHaveCount(1)
        ->and($result->components[0])
        ->toBeInstanceOf(AddressComponent::class)
        ->longName->toBe('Very long name')
        ->shortName->toBe('Short name')
        ->types->toBe(['one', 'two']);
});
