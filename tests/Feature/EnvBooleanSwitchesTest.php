<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\HttpClientRateLimits\Facades\RateLimits;

/**
 * env() only turns 'true'/'false' into booleans: a .env "1"/"on"/"yes" stays a string
 * (so `=== true` reads it as off) and `(bool) 'off'` is true. Every switch must read
 * these the way a host means them — and the about rows must agree with the behaviour.
 */
dataset('places env truthy', ['1', 'on', 'yes', 'true']);
dataset('places env falsy', ['0', 'off', 'no', 'false']);

function aboutGooglePlaces(): string
{
    Artisan::call('about', ['--only' => 'google-places']);

    return Artisan::output();
}

/**
 * @return array<string, mixed>
 */
function googlePlacesConfigWithEnv(string $name, string $value): array
{
    $_SERVER[$name] = $value;

    try {
        return require __DIR__.'/../../config/google-places.php';
    } finally {
        unset($_SERVER[$name]);
    }
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake(['places.googleapis.com/v1/places/*' => Http::response(placeResponse())]);
});

it('caches and logs when the switches hold an env-style truthy string', function (string $value): void {
    config()->set('google-places.cache.enabled', $value);
    config()->set('google-places.logging.enabled', $value);
    config()->set('google-places.logging.channel', 'stack');
    Log::shouldReceive('channel')->with('stack')->andReturnSelf();
    Log::shouldReceive('info')->once();

    places()->details(new DetailsQuery('place-1'));
    places()->details(new DetailsQuery('place-1'));

    Http::assertSentCount(1);
    expect(aboutGooglePlaces())
        ->toMatch('/Cache\s*\.*\s*ENABLED/')
        ->toMatch('/Logging\s*\.*\s*ENABLED/');
})->with('places env truthy');

it('neither caches nor logs when the switches hold an env-style falsy string', function (string $value): void {
    config()->set('google-places.cache.enabled', $value);
    config()->set('google-places.logging.enabled', $value);
    Log::spy();

    places()->details(new DetailsQuery('place-1'));
    places()->details(new DetailsQuery('place-1'));

    Http::assertSentCount(2);
    Log::shouldNotHaveReceived('channel');
    expect(aboutGooglePlaces())
        ->toMatch('/Cache\s*\.*\s*OFF/')
        ->toMatch('/Logging\s*\.*\s*OFF/');
})->with('places env falsy');

it('keeps the shipped config from turning an env "off" into true', function (string $value): void {
    config()->set('google-places.cache', googlePlacesConfigWithEnv('GOOGLE_PLACES_CACHE', $value)['cache']);
    config()->set('google-places.logging', googlePlacesConfigWithEnv('GOOGLE_PLACES_LOGGING', $value)['logging']);
    Log::spy();

    places()->details(new DetailsQuery('place-1'));
    places()->details(new DetailsQuery('place-1'));

    Http::assertSentCount(2);
    Log::shouldNotHaveReceived('channel');
    expect(aboutGooglePlaces())
        ->toMatch('/Cache\s*\.*\s*OFF/')
        ->toMatch('/Logging\s*\.*\s*OFF/');
})->with('places env falsy');

it('bypasses the limiter when a surface switch is an env-style "off"', function (): void {
    config()->set('google-places.rate_limits.places.enabled', 'off');
    config()->set('google-places.rate_limits.places.limit', 1);
    $fake = RateLimits::fake();

    places()->details(new DetailsQuery('place-1'));
    places()->details(new DetailsQuery('place-2'));

    $fake->assertNothingDeferred();
    expect($fake->allowedCount())->toBe(0);
});

it('reads the adaptive switch as a boolean', function (string $value, bool $adaptive): void {
    config()->set('google-places.rate_limits.geocoding.enabled', '1');
    config()->set('google-places.rate_limits.geocoding.adaptive', $value);
    $fake = RateLimits::fake();
    Http::fake(['maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'OVER_QUERY_LIMIT'], 429, ['Retry-After' => '2'])]);

    rescue(fn () => places()->geocodeAddress('a'), report: false);
    rescue(fn () => places()->geocodeAddress('b'), report: false);

    // Throttled either way ('1' is on); only an adaptive limiter turns the 429's
    // Retry-After into a penalty that defers the next call.
    expect($fake->allowedCount())->toBeGreaterThan(0)
        ->and($fake->deferredCount() > 0)->toBe($adaptive);
})->with([
    'on' => ['on', true],
    'yes' => ['yes', true],
    'off' => ['off', false],
    'no' => ['no', false],
]);
