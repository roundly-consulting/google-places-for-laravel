<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\AddressComponent;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AddressComponents;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Geometry;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;

function sampleComponents(): array
{
    return [
        AddressComponent::fromResponse(['long_name' => '1600', 'short_name' => '1600', 'types' => ['street_number']]),
        AddressComponent::fromResponse(['long_name' => 'Amphitheatre Parkway', 'short_name' => 'Amphitheatre Pkwy', 'types' => ['route']]),
        AddressComponent::fromResponse(['long_name' => 'Mountain View', 'short_name' => 'Mountain View', 'types' => ['locality', 'political']]),
        AddressComponent::fromResponse(['long_name' => 'California', 'short_name' => 'CA', 'types' => ['administrative_area_level_1']]),
        AddressComponent::fromResponse(['long_name' => '94043', 'short_name' => '94043', 'types' => ['postal_code']]),
        AddressComponent::fromResponse(['long_name' => 'United States', 'short_name' => 'US', 'types' => ['country']]),
    ];
}

it('exposes typed accessors over the components', function () {
    $components = new AddressComponents(sampleComponents());

    expect($components)
        ->streetNumber()->toBe('1600')
        ->street()->toBe('Amphitheatre Parkway')
        ->city()->toBe('Mountain View')
        ->state()->toBe('California')
        ->postalCode()->toBe('94043')
        ->country()->toBe('United States')
        ->countryCode()->toBe('US');
});

it('returns null for missing component types', function () {
    $components = new AddressComponents([]);

    expect($components)
        ->city()->toBeNull()
        ->country()->toBeNull()
        ->countryCode()->toBeNull()
        ->and($components->has('locality'))->toBeFalse()
        ->and($components->first('locality'))->toBeNull();
});

it('falls back to postal_town for the city', function () {
    $components = new AddressComponents([
        AddressComponent::fromResponse(['long_name' => 'Cambridge', 'short_name' => 'Cambridge', 'types' => ['postal_town']]),
    ]);

    expect($components->city())->toBe('Cambridge');
});

it('is reachable through a geocoding result', function () {
    $result = new ReverseGeocodingResult(
        address: 'Somewhere',
        placeId: 'g1',
        geometry: Geometry::fromResponse([
            'location' => ['lat' => 1, 'lng' => 2],
            'viewport' => ['northeast' => ['lat' => 3, 'lng' => 4], 'southwest' => ['lat' => 5, 'lng' => 6]],
        ]),
        types: ['locality'],
        components: sampleComponents(),
    );

    expect($result->components())
        ->toBeInstanceOf(AddressComponents::class)
        ->city()->toBe('Mountain View');
});
