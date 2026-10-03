<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\Exceptions\RateLimitExceededException;
use RoundlyConsulting\HttpClientRateLimits\Facades\RateLimits;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Owner rule: a typo in a host's config or env fails loudly and never falls back silently.
 * Every number is an int or a canonical integer string, every string setting a non-empty
 * string, and the rate-limit window one of the four Timespans. Only an unset (null) key
 * takes its default.
 *
 * @return array<string, mixed>
 */
function googlePlacesConfigFromEnv(string $name, string $value): array
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

it('passes numeric env values through raw so the reader sees the typo (strict config)', function (string $env, string $key) {
    expect(data_get(googlePlacesConfigFromEnv($env, 'five'), $key))->toBe('five');
})->with([
    ['GOOGLE_PLACES_TIMEOUT', 'http.timeout'],
    ['GOOGLE_PLACES_CONNECT_TIMEOUT', 'http.connect_timeout'],
    ['GOOGLE_PLACES_RETRIES', 'http.retries'],
    ['GOOGLE_PLACES_RETRY_DELAY', 'http.retry_delay'],
    ['GOOGLE_PLACES_CACHE_TTL', 'cache.ttl'],
    ['GOOGLE_PLACES_MAX_PAGES', 'pagination.max_pages'],
    ['GOOGLE_PLACES_PLACES_RATELIMIT', 'rate_limits.places.limit'],
    ['GOOGLE_PLACES_ROUTES_RATELIMIT', 'rate_limits.routes.limit'],
    ['GOOGLE_PLACES_GEOCODING_RATELIMIT', 'rate_limits.geocoding.limit'],
]);

it('refuses a junk or out-of-range http number instead of casting it (strict config)', function (string $key, mixed $value, string $expected) {
    config()->set("google-places.{$key}", $value);

    expect(fn () => places()->details(new DetailsQuery('place-1')))->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [google-places.{$key}] must be {$expected}",
    );
})->with([
    'timeout word' => ['http.timeout', 'five', 'an integer'],
    'timeout zero' => ['http.timeout', '0', 'at least 1'],
    'connect timeout suffix' => ['http.connect_timeout', '5s', 'an integer'],
    'connect timeout zero' => ['http.connect_timeout', 0, 'at least 1'],
    'retries word' => ['http.retries', 'two', 'an integer'],
    'retries negative' => ['http.retries', -1, 'at least 0'],
    'retry delay empty env' => ['http.retry_delay', '', 'an integer'],
    'retry delay negative' => ['http.retry_delay', '-5', 'at least 0'],
]);

it('reads canonical integer strings for the http settings (strict config)', function () {
    config()->set('google-places.http.timeout', '10');
    config()->set('google-places.http.connect_timeout', ' 5 ');
    config()->set('google-places.http.retries', '0');
    config()->set('google-places.http.retry_delay', '0');

    expect(places()->details(new DetailsQuery('place-1'))->name)->not->toBeNull();
});

it('refuses a junk or zero cache ttl (strict config)', function (mixed $value, string $expected) {
    config()->set('google-places.cache.enabled', true);
    config()->set('google-places.cache.ttl', $value);

    expect(fn () => places()->details(new DetailsQuery('place-1')))->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [google-places.cache.ttl] must be {$expected}",
    );
})->with([
    'word' => ['day', 'an integer'],
    'float' => ['1.5', 'an integer'],
    'zero' => [0, 'at least 1'],
]);

it('caches with an env-string ttl (strict config)', function () {
    config()->set('google-places.cache.enabled', true);
    config()->set('google-places.cache.ttl', '60');

    places()->details(new DetailsQuery('place-1'));
    places()->details(new DetailsQuery('place-1'));

    Http::assertSentCount(1);
});

it('refuses a blank or non-string cache store instead of using the default (strict config)', function (mixed $value) {
    config()->set('google-places.cache.enabled', true);
    config()->set('google-places.cache.store', $value);

    expect(fn () => places()->details(new DetailsQuery('place-1')))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [google-places.cache.store] must be a non-empty string',
    );
})->with(['empty env' => [''], 'int' => [5]]);

it('refuses a blank or non-string log channel instead of using the default (strict config)', function (mixed $value) {
    config()->set('google-places.logging.enabled', true);
    config()->set('google-places.logging.channel', $value);

    expect(fn () => places()->details(new DetailsQuery('place-1')))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [google-places.logging.channel] must be a non-empty string',
    );
})->with(['empty env' => [''], 'list' => [['stack']]]);

it('logs to the default channel when none is configured (strict config)', function () {
    config()->set('google-places.logging.enabled', true);
    config()->set('google-places.logging.channel', null);
    Log::shouldReceive('channel')->with(null)->andReturnSelf();
    Log::shouldReceive('info')->once();

    places()->details(new DetailsQuery('place-1'));
});

it('refuses a blank or non-string host or field mask (strict config)', function (string $key, mixed $value) {
    config()->set("google-places.{$key}", $value);

    expect(fn () => places()->details(new DetailsQuery('place-1')))->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [google-places.{$key}] must be a non-empty string",
    );
})->with([
    'host empty env' => ['hosts.places', ''],
    'host int' => ['hosts.places', 443],
    'details mask list' => ['field_masks.details', ['id']],
    'details mask blank' => ['field_masks.details', ' '],
]);

it('refuses a blank search field mask (strict config)', function () {
    config()->set('google-places.field_masks.search', '');
    Http::fake(['places.googleapis.com/v1/places:searchText' => Http::response(['places' => []])]);

    expect(fn () => places()->textSearch('museums'))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [google-places.field_masks.search] must be a non-empty string',
    );
});

it('refuses a junk rate-limit setting instead of guessing one (strict config)', function (string $key, mixed $value, string $expected) {
    config()->set("google-places.rate_limits.{$key}", $value);
    RateLimits::fake();

    expect(fn () => places()->details(new DetailsQuery('place-1')))->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [google-places.rate_limits.{$key}] must be {$expected}",
    );
})->with([
    'limit word' => ['places.limit', 'lots', 'an integer'],
    'limit zero' => ['places.limit', '0', 'at least 1'],
    'per typo' => ['places.per', 'minutes', 'one of [second, minute, hour, day]'],
    'per capitalised' => ['places.per', 'Minute', 'one of [second, minute, hour, day]'],
    'max wait word' => ['places.max_wait', 'soon', 'an integer'],
    'max wait empty env' => ['places.max_wait', '', 'an integer'],
    'max wait negative' => ['places.max_wait', -1, 'at least 0'],
    'jitter suffix' => ['places.jitter', '50ms', 'an integer'],
    'owner empty env' => ['owner', '', 'a non-empty string'],
    'owner list' => ['owner', ['app'], 'a non-empty string'],
]);

it('reads env-string rate limits through the strict reader (strict config)', function () {
    config()->set('google-places.rate_limits.places.limit', '1');
    config()->set('google-places.rate_limits.places.per', 'hour');
    config()->set('google-places.rate_limits.places.max_wait', '0');
    config()->set('google-places.rate_limits.places.jitter', '0');
    config()->set('google-places.rate_limits.owner', 'workers');
    $fake = RateLimits::fake();

    places()->details(new DetailsQuery('place-1'));
    $fake->assertAllowed('google-places:places:workers');

    try {
        places()->details(new DetailsQuery('place-1'));
    } catch (RateLimitExceededException $e) {
        // An hour window: far beyond the minute a `per` typo used to fall back to.
        expect($e->retryAfterSeconds())->toBeGreaterThan(60);

        return;
    }

    $this->fail('Expected a RateLimitExceededException.');
});

it('uses the documented rate-limit defaults when the keys are unset (strict config)', function () {
    foreach (['places.limit', 'places.per', 'places.max_wait', 'places.jitter', 'owner'] as $key) {
        config()->set("google-places.rate_limits.{$key}", null);
    }

    $fake = RateLimits::fake();

    places()->details(new DetailsQuery('place-1'));

    $fake->assertAllowed('google-places:places:app');
});
