<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixQuery;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

beforeEach(fn () => Http::preventStrayRequests());

it('dispatches a response-received event on success', function () {
    Event::fake();

    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);

    places()->details(new DetailsQuery('place-1'));

    Event::assertDispatched(PlacesResponseReceived::class, function (PlacesResponseReceived $event): bool {
        return $event->endpoint === 'details'
            && $event->httpStatus === 200
            && ! str_contains((string) json_encode((array) $event), 'GoogleApiKey');
    });
});

it('dispatches a request-failed event with a safe message', function () {
    Event::fake();

    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response([
            'error' => ['status' => 'PERMISSION_DENIED', 'message' => 'The provided API key is invalid.'],
        ], 403),
    ]);

    try {
        places()->details(new DetailsQuery('place-1'));
    } catch (PlacesException) {
        // expected
    }

    Event::assertDispatched(PlacesRequestFailed::class, function (PlacesRequestFailed $event): bool {
        return $event->endpoint === 'details'
            && $event->httpStatus === 403
            && $event->googleStatus === 'PERMISSION_DENIED'
            && $event->message === 'The provided API key is invalid.'
            && ! str_contains((string) json_encode((array) $event), 'GoogleApiKey');
    });
});

it('dispatches only a failure event for a successful response with an unusable body', function (string $call, mixed $body) {
    Event::fake();

    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response($body),
    ]);

    $request = $call === 'distance'
        ? fn () => places()->distance(new DistanceQuery(new Location(1, 2), new Location(3, 4)))
        : fn () => places()->computeMatrix(new MatrixQuery([new Location(1, 2)], [new Location(3, 4)]));

    expect($request)->toThrow(PlacesException::class);

    Event::assertDispatchedTimes(PlacesRequestFailed::class, 1);
    Event::assertNotDispatched(PlacesResponseReceived::class);
})->with([
    'distance, empty answer' => ['distance', []],
    'matrix, not json' => ['computeMatrix', 'upstream proxy says hi'],
]);

it('dispatches exactly one event per request, and none for a call that sends nothing', function () {
    Event::fake();
    config()->set('google-places.cache.enabled', true);

    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);

    places()->details(new DetailsQuery('place-1'));
    places()->details(new DetailsQuery('place-1')); // cache hit: no request, no event
    places()->photoUrl('places/p/photos/a');          // built locally: no request, no event

    Event::assertDispatchedTimes(PlacesResponseReceived::class, 1);
    Event::assertNotDispatched(PlacesRequestFailed::class);
    Http::assertSentCount(1);
});

it('dispatches no lifecycle event for the check probes or a call refused before sending', function () {
    Event::fake();

    Http::fake([
        'places.googleapis.com/*' => Http::response(['places' => []]),
        'routes.googleapis.com/*' => Http::response([]),
        'maps.googleapis.com/*' => Http::response(['status' => 'OK', 'results' => []]),
    ]);

    places()->check();

    config()->set('google-places.key', null);
    expect(fn () => places()->textSearch('coffee'))->toThrow(PlacesException::class, 'API key is missing');

    Event::assertNotDispatched(PlacesResponseReceived::class);
    Event::assertNotDispatched(PlacesRequestFailed::class);
});
