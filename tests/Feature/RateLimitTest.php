<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\HttpClientRateLimits\Facades\RateLimits;

function geocodeOk(): array
{
    return ['maps.googleapis.com/maps/api/geocode/json*' => Http::response([
        'status' => 'OK',
        'results' => [[
            'formatted_address' => 'Somewhere',
            'place_id' => 'p1',
            'geometry' => [
                'location' => ['lat' => 1.0, 'lng' => 2.0],
                'viewport' => [
                    'northeast' => ['lat' => 1.1, 'lng' => 2.1],
                    'southwest' => ['lat' => 0.9, 'lng' => 1.9],
                ],
            ],
            'types' => ['locality'],
            'address_components' => [],
        ]],
    ])];
}

it('paces an allowed geocoding call under its per-surface key', function () {
    $fake = RateLimits::fake();
    Http::fake(geocodeOk());

    places()->geocodeAddress('1 Main St');

    $fake->assertAllowed('google-places:geocoding:app')->assertNothingDeferred();
});

it('defers the next call once a surface window is exhausted', function () {
    config()->set('google-places.rate_limits.geocoding.limit', 1);
    $fake = RateLimits::fake();
    Http::fake(geocodeOk());

    places()->geocodeAddress('a');
    places()->geocodeAddress('b');

    $fake->assertDeferred('google-places:geocoding:app');
});

it('throws a typed PlacesException when max_wait is exceeded', function () {
    config()->set('google-places.rate_limits.geocoding.limit', 1);
    config()->set('google-places.rate_limits.geocoding.max_wait', 10);
    RateLimits::fake();
    Http::fake(geocodeOk());

    places()->geocodeAddress('a');

    try {
        places()->geocodeAddress('b');
        $this->fail('Expected a rate-limited PlacesException.');
    } catch (PlacesException $exception) {
        expect($exception->rateLimitedSurface)->toBe('geocoding')
            ->and($exception->availableInSeconds)->toBeGreaterThan(0)
            ->and($exception->getCode())->toBe(429);
    }
});

it('bypasses the limiter entirely when a surface is disabled', function () {
    config()->set('google-places.rate_limits.geocoding.enabled', false);
    config()->set('google-places.rate_limits.geocoding.limit', 1);
    $fake = RateLimits::fake();
    Http::fake(geocodeOk());

    places()->geocodeAddress('a');
    places()->geocodeAddress('b');

    $fake->assertNothingDeferred();
    expect($fake->allowedCount())->toBe(0);
});

it('records an adaptive penalty from a 429 Retry-After and defers the next call', function () {
    $fake = RateLimits::fake();
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'OVER_QUERY_LIMIT'], 429, ['Retry-After' => '2']),
    ]);

    // The 429 surfaces as a PlacesException, but the limiter reads Retry-After
    // off the raw response first and records the server penalty.
    expect(fn () => places()->geocodeAddress('a'))->toThrow(PlacesException::class);

    rescue(fn () => places()->geocodeAddress('b'));

    $fake->assertDeferred('google-places:geocoding:app');
});

it('keeps per-surface budgets isolated', function () {
    config()->set('google-places.rate_limits.geocoding.limit', 1);
    config()->set('google-places.rate_limits.places.limit', 1);
    $fake = RateLimits::fake();
    Http::fake([
        ...geocodeOk(),
        'places.googleapis.com/*' => Http::response(['places' => []]),
    ]);

    places()->geocodeAddress('a');
    places()->geocodeAddress('b');
    places()->textSearch('coffee');

    $fake->assertDeferred('google-places:geocoding:app')
        ->assertAllowed('google-places:places:app');

    // Only geocoding was deferred — the places surface's distinct key was untouched.
    expect($fake->deferredCount())->toBe(1);
});
