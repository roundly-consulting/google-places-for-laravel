<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;
use RoundlyConsulting\GooglePlaces\Support\PlacesSession;

beforeEach(fn () => Http::preventStrayRequests());

it('reuses one session token across autocomplete and details', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response(['suggestions' => []]),
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);

    $session = GooglePlaces::session();
    $token = $session->token();

    $session->autocomplete('piz');
    $session->autocomplete('pizza');
    $session->details('place-1');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'autocomplete')
        && ($request['sessionToken'] ?? null) === $token);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/places/place-1')
        && str_contains($request->url(), 'sessionToken='.$token));
});

it('accepts a details query object and keeps its field overrides', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);

    $session = GooglePlaces::session('fixed-token');

    $session->details(new DetailsQuery('place-1', fields: ['id', 'displayName']));

    Http::assertSent(fn (Request $request): bool => $request->header('X-Goog-FieldMask')[0] === 'id,displayName'
        && str_contains($request->url(), 'sessionToken=fixed-token'));
});

it('closes the session after details and rejects further use', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);

    $session = GooglePlaces::session();

    expect($session->isFinished())->toBeFalse();

    $session->details('place-1');

    expect($session->isFinished())->toBeTrue();

    $session->autocomplete('again');
})->throws(PlacesException::class, 'already been closed');

it('builds a session bound to the swapped fake client', function () {
    $fake = GooglePlaces::fake();

    $session = GooglePlaces::session();

    expect($session)->toBeInstanceOf(PlacesSession::class);

    $session->autocomplete('coffee');

    $fake->assertAutocompleted(fn ($q): bool => $q->sessionToken === $session->token());
});
