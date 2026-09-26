<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

function fakeHealthyApis(): void
{
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => []]),
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([]),
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'OK', 'results' => []]),
    ]);
}

it('reports success when every api is reachable', function () {
    fakeHealthyApis();

    $this->artisan('google-places:check')
        ->expectsOutputToContain('All configured Google APIs are enabled and reachable.')
        ->assertExitCode(0);
});

it('redacts the api key in its output', function () {
    fakeHealthyApis();

    $this->artisan('google-places:check')
        ->expectsOutputToContain('********iKey')
        ->doesntExpectOutputToContain('GoogleApiKey')
        ->assertExitCode(0);
});

it('fails and reports the offending api', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['error' => ['status' => 'PERMISSION_DENIED', 'message' => 'denied']], 403),
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([]),
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'OK', 'results' => []]),
    ]);

    $this->artisan('google-places:check')
        ->expectsOutputToContain('One or more Google APIs are not reachable or not enabled.')
        ->assertExitCode(1);
});

it('reports an unreachable api', function () {
    Http::fake(fn () => throw new ConnectionException('network down'));

    $this->artisan('google-places:check')
        ->expectsOutputToContain('not reachable')
        ->assertExitCode(1);
});

it('fails when no api key is configured', function () {
    config()->set('google-places.key', null);

    $this->artisan('google-places:check')
        ->expectsOutputToContain('No API key configured')
        ->assertExitCode(1);
});

it('fails the geocoding probe when Google answers 200 with REQUEST_DENIED', function () {
    // Recorded live: the legacy Geocoding API answers an invalid key with HTTP 200 and
    // `status: REQUEST_DENIED`, so an HTTP-level success is not "authorized".
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => []]),
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([]),
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(
            json_decode((string) file_get_contents(__DIR__.'/../Fixtures/errors/geocoding-request-denied.json'), true),
        ),
    ]);

    $this->artisan('google-places:check')
        ->expectsOutputToContain('REQUEST_DENIED')
        ->expectsOutputToContain('One or more Google APIs are not reachable or not enabled.')
        ->assertExitCode(1);
});

it('reports the status and message of an array-wrapped Routes API error', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => []]),
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response(
            json_decode((string) file_get_contents(__DIR__.'/../Fixtures/errors/routes-api-key-invalid.json'), true),
            400,
        ),
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'OK', 'results' => []]),
    ]);

    $this->artisan('google-places:check')
        ->expectsOutputToContain('400 INVALID_ARGUMENT API key not valid')
        ->assertExitCode(1);
});

it('passes a geocoding probe that finds nothing', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => []]),
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([]),
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []]),
    ]);

    $this->artisan('google-places:check')->assertExitCode(0);
});
