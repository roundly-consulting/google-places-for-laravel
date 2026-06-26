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
