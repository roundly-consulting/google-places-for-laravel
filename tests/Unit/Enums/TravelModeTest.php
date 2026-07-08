<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

it('maps every mode to its Routes API value', function () {
    expect(TravelMode::Driving->routesValue())->toBe('DRIVE')
        ->and(TravelMode::Walking->routesValue())->toBe('WALK')
        ->and(TravelMode::Bicycling->routesValue())->toBe('BICYCLE')
        ->and(TravelMode::Transit->routesValue())->toBe('TRANSIT');
});

it('exposes the legacy string values', function () {
    expect(TravelMode::Driving->value)->toBe('driving')
        ->and(TravelMode::from('walking'))->toBe(TravelMode::Walking);
});

it('exposes the enums Helpers value surface', function () {
    expect(TravelMode::values()->all())->toBe(['driving', 'walking', 'bicycling', 'transit'])
        ->and(TravelMode::names()->all())->toBe(['Driving', 'Walking', 'Bicycling', 'Transit'])
        ->and(TravelMode::count())->toBe(4)
        ->and(TravelMode::hasValue('transit'))->toBeTrue()
        ->and(TravelMode::hasValue('flying'))->toBeFalse();
});

it('builds a validation rule from the backed values', function () {
    expect(TravelMode::validationRule())->toBe('in:driving,walking,bicycling,transit');
});

it('renders readable labels and select options', function () {
    expect(TravelMode::Driving->readable())->toBe('Driving')
        ->and(TravelMode::Bicycling->label())->toBe('Bicycling')
        ->and(TravelMode::labels()->all())->toBe(['Driving', 'Walking', 'Bicycling', 'Transit'])
        ->and(TravelMode::toArray())->toBe([
            'driving' => 'Driving',
            'walking' => 'Walking',
            'bicycling' => 'Bicycling',
            'transit' => 'Transit',
        ]);

    $options = TravelMode::options();

    expect($options)->toHaveCount(4)
        ->and($options->first()->value)->toBe('driving')
        ->and($options->first()->label)->toBe('Driving')
        ->and($options->first()->name)->toBe('Driving');
});

it('resolves cases by name and label', function () {
    expect(TravelMode::fromName('Transit'))->toBe(TravelMode::Transit)
        ->and(TravelMode::tryFromName('nope'))->toBeNull()
        ->and(TravelMode::tryFromLabel('Bicycling'))->toBe(TravelMode::Bicycling)
        ->and(TravelMode::fromLabel('Walking'))->toBe(TravelMode::Walking);
});

it('compares cases with the Helpers instance guards', function () {
    expect(TravelMode::Driving->is(TravelMode::Driving))->toBeTrue()
        ->and(TravelMode::Driving->isNot(TravelMode::Walking))->toBeTrue()
        ->and(TravelMode::Transit->isIn([TravelMode::Transit, TravelMode::Walking]))->toBeTrue()
        ->and(TravelMode::Driving->isNotIn([TravelMode::Transit]))->toBeTrue();
});
