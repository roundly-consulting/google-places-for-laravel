<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
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
