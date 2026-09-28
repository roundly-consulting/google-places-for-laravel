<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ApiCheckResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;
use RoundlyConsulting\GooglePlaces\Support\PendingMatrix;
use RoundlyConsulting\GooglePlaces\Support\PendingPhoto;
use RoundlyConsulting\GooglePlaces\Support\PlacesSession;

/*
 * No toReachEveryAction(): google-places is a remote-API client and has no
 * src/Actions — its service object (the PlacesClient contract) IS the API.
 */
it('pins the facade contract', function () {
    expect(GooglePlaces::class)
        ->toDocumentItsRoot()
        ->toBeFakeable();
});

it('hands out the session, matrix and photo handles from the client itself', function () {
    expect(GooglePlaces::session('tok'))->toBeInstanceOf(PlacesSession::class)
        ->and(GooglePlaces::session('tok')->token())->toBe('tok')
        ->and(GooglePlaces::matrix([new Location(1, 2)], [new Location(3, 4)]))->toBeInstanceOf(PendingMatrix::class)
        ->and(GooglePlaces::photo('places/p1/photos/a'))->toBeInstanceOf(PendingPhoto::class);
});

it('works without the facade through the injected contract', function () {
    Http::preventStrayRequests();
    Http::fake(['places.googleapis.com/v1/*' => Http::response('BYTES', 200, ['Content-Type' => 'image/jpeg'])]);

    $places = app(PlacesClient::class);

    expect($places->photo('places/p1/photos/a', 200, 100)->contents())->toBe('BYTES')
        ->and($places->photoContents('places/p1/photos/a'))->toBe('BYTES');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'places/p1/photos/a/media')
        && str_contains($request->url(), 'maxWidthPx=200'));
});

it('probes every api through the facade and returns typed results', function () {
    Http::preventStrayRequests();
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => []]),
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([]),
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'REQUEST_DENIED', 'error_message' => 'The provided API key GoogleApiKey is invalid.']),
    ]);

    $results = GooglePlaces::check();

    expect($results)->toHaveCount(3)
        ->each->toBeInstanceOf(ApiCheckResult::class)
        ->and(array_map(fn (ApiCheckResult $r): string => $r->api, $results))
        ->toBe(['Places API (New)', 'Routes API', 'Geocoding API'])
        ->and($results[0]->ok)->toBeTrue()
        ->and($results[1]->ok)->toBeTrue()
        ->and($results[2]->ok)->toBeFalse()
        ->and($results[2]->detail)->toContain('REQUEST_DENIED')
        ->and($results[2]->detail)->toContain('********iKey')
        ->and($results[2]->detail)->not->toContain('GoogleApiKey');
});

it('refuses to probe without an api key', function () {
    config()->set('google-places.key', '');

    GooglePlaces::check();
})->throws(PlacesException::class, 'API key is missing');

it('redacts the key from an unreachable probe', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error for https://maps.googleapis.com/?key=GoogleApiKey'));

    $results = GooglePlaces::check();

    expect($results[2]->ok)->toBeFalse()
        ->and($results[2]->detail)->toStartWith('Unreachable: ')
        ->and($results[2]->detail)->not->toContain('GoogleApiKey');
});
