<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;
use RoundlyConsulting\GooglePlaces\Places;

beforeEach(fn () => Http::preventStrayRequests());

it('resolves the client as a singleton from the container', function () {
    expect(app(Places::class))
        ->toBeInstanceOf(Places::class)
        ->and(app(Places::class))->toBe(app(Places::class));
});

it('proxies calls through the facade', function () {
    $url = GooglePlaces::photoUrl('hash', 100, 150);

    expect($url)->toBe('https://maps.googleapis.com/maps/api/place/photo?photo_reference=hash&maxwidth=100&maxheight=150&key=GoogleApiKey');
});

it('runs autocomplete through the facade', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/place/autocomplete/json?*' => Http::response(body: ['status' => 'ZERO_RESULTS']),
    ]);

    expect(GooglePlaces::autocomplete(new AutocompleteQuery))
        ->toBeInstanceOf(Collection::class)
        ->isEmpty()->toBeTrue();
});
