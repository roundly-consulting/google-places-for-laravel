<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\AddressComponents;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;

function placeWithHours(): Place
{
    return Place::fromResponse([
        'id' => 'p1',
        'displayName' => ['text' => 'Café'],
        'types' => ['cafe', 'food'],
        'primaryType' => 'cafe',
        'location' => ['latitude' => 1.5, 'longitude' => 2.5],
        'regularOpeningHours' => [
            'openNow' => true,
            'periods' => [
                ['open' => ['day' => 1, 'hour' => 9, 'minute' => 0], 'close' => ['day' => 1, 'hour' => 17, 'minute' => 0]],
                ['open' => ['day' => 2, 'hour' => 10, 'minute' => 0], 'close' => ['day' => 2, 'hour' => 18, 'minute' => 0]],
            ],
        ],
        'addressComponents' => [
            ['longText' => 'Bratislava', 'shortText' => 'BA', 'types' => ['locality']],
            ['longText' => 'Slovakia', 'shortText' => 'SK', 'types' => ['country']],
        ],
    ]);
}

it('reports whether the place is open now', function () {
    expect(placeWithHours()->isOpenNow())->toBeTrue();
    expect((new Place('Closed'))->isOpenNow())->toBeFalse();
});

it('returns the coordinates as a location', function () {
    expect(placeWithHours()->coordinates())
        ->toBeInstanceOf(Location::class)
        ->latitude->toBe(1.5)
        ->longitude->toBe(2.5);

    expect((new Place('No geo'))->coordinates())->toBeNull();
});

it('resolves the primary type with a fallback to the first type', function () {
    expect(placeWithHours()->primaryType())->toBe('cafe');
    expect((new Place('X', types: ['museum']))->primaryType())->toBe('museum');
    expect((new Place('X'))->primaryType())->toBeNull();
});

it('returns opening-hour periods for a given day', function () {
    expect(placeWithHours()->openingHoursFor(1))->toHaveCount(1);
    expect(placeWithHours()->openingHoursFor(1)[0]->openHour)->toBe(9);
    expect(placeWithHours()->openingHoursFor(3))->toBe([]);
    expect((new Place('No hours'))->openingHoursFor(1))->toBe([]);
});

it('exposes typed address components from a place', function () {
    expect(placeWithHours()->components())
        ->toBeInstanceOf(AddressComponents::class)
        ->city()->toBe('Bratislava')
        ->countryCode()->toBe('SK');
});
