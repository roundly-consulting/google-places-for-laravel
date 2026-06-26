<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Listeners\LogPlacesActivity;

beforeEach(fn () => Http::preventStrayRequests());

it('does not log when logging is disabled', function () {
    Log::spy();

    Http::fake(['places.googleapis.com/v1/places/*' => Http::response(placeResponse())]);

    places()->details(new DetailsQuery('place-1'));

    Log::shouldNotHaveReceived('channel');
});

it('logs a successful request to the configured channel when enabled', function () {
    config()->set('google-places.logging.enabled', true);
    config()->set('google-places.logging.channel', 'stack');

    Log::shouldReceive('channel')->with('stack')->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context): bool {
        return $message === 'google-places request'
            && $context['endpoint'] === 'details'
            && $context['http_status'] === 200
            && ! str_contains((string) json_encode($context), 'GoogleApiKey');
    });

    Http::fake(['places.googleapis.com/v1/places/*' => Http::response(placeResponse())]);

    places()->details(new DetailsQuery('place-1'));
});

it('logs a failed request as a warning', function () {
    config()->set('google-places.logging.enabled', true);

    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context): bool {
        return $message === 'google-places request failed'
            && $context['endpoint'] === 'details'
            && $context['http_status'] === 403;
    });

    Http::fake(['places.googleapis.com/v1/places/*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);

    try {
        places()->details(new DetailsQuery('place-1'));
    } catch (PlacesException) {
        // expected
    }
});

it('honours the default channel when none is configured', function () {
    config()->set('google-places.logging.enabled', true);

    $listener = new LogPlacesActivity;

    Log::shouldReceive('channel')->with(null)->andReturnSelf();
    Log::shouldReceive('info')->once();

    $listener->handleResponseReceived(new PlacesResponseReceived('details', 200, 'OK', 12));
});

it('ignores events while disabled even when invoked directly', function () {
    config()->set('google-places.logging.enabled', false);

    Log::spy();

    $listener = new LogPlacesActivity;

    $listener->handleResponseReceived(new PlacesResponseReceived('details', 200));
    $listener->handleRequestFailed(new PlacesRequestFailed('details', 500));

    Log::shouldNotHaveReceived('channel');
});
