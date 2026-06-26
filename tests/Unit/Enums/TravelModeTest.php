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
