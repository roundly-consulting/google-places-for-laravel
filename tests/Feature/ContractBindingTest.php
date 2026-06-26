<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;
use RoundlyConsulting\GooglePlaces\Places;

it('binds the contract to a Places singleton', function () {
    expect(app(PlacesClient::class))
        ->toBeInstanceOf(Places::class)
        ->and(app(PlacesClient::class))->toBe(app(PlacesClient::class));
});

it('resolves the concrete class to the same singleton', function () {
    expect(app(Places::class))->toBe(app(PlacesClient::class));
});

it('points the facade accessor at the contract', function () {
    expect(GooglePlaces::getFacadeRoot())->toBeInstanceOf(Places::class);
});
