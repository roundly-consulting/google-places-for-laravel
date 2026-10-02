<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Listeners\LogPlacesActivity;

const SECRET_KEY = 'AIzaSECRETKEY1234';

beforeEach(function () {
    config()->set('google-places.key', SECRET_KEY);
    config()->set('google-places.http.retries', 0);
});

/**
 * Run a call that must fail, and return the exception it threw.
 */
function failingCall(Closure $call): PlacesException
{
    try {
        $call();
    } catch (PlacesException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a PlacesException.');
}

it('keeps the key out of the exception, the event and the log when the geocoding host is unreachable', function () {
    // Nothing listens on the discard port: a real, immediate connection refusal whose
    // transport message ends in the full request URL — key query parameter included.
    config()->set('google-places.hosts.geocoding', 'http://127.0.0.1:9/maps/api');
    config()->set('google-places.http.connect_timeout', 2);
    config()->set('google-places.logging.enabled', true);

    $events = [];
    Event::listen(PlacesRequestFailed::class, function (PlacesRequestFailed $event) use (&$events): void {
        $events[] = $event;
    });

    $logged = [];
    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context) use (&$logged): bool {
        $logged = $context;

        return true;
    });

    $exception = failingCall(fn () => places()->geocodeAddress('Bratislava'));

    expect($exception->getMessage())
        ->toContain('Could not reach the Google Places API')
        ->toContain('127.0.0.1')
        ->not->toContain(SECRET_KEY)
        ->not->toContain('SECRETKEY')
        ->and($events)->toHaveCount(1)
        ->and($events[0]->httpStatus)->toBe(0)
        ->and($events[0]->message)->not->toContain('SECRETKEY')
        ->and((string) json_encode($logged))->not->toContain('SECRETKEY');
});

it('redacts the key from a connection failure on every geocoding call', function (string $method) {
    Http::fake(fn (Request $request) => throw new ConnectionException(
        "cURL error 28: Operation timed out (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for {$request->url()}",
    ));
    Event::fake([PlacesRequestFailed::class]);

    $exception = failingCall(fn () => $method === 'geocode'
        ? places()->geocode(48.1486, 17.1077)
        : places()->geocodeAddress('Bratislava'));

    expect($exception->getMessage())
        ->toContain('key=')
        ->toContain('1234')
        ->not->toContain('SECRETKEY');

    Event::assertDispatched(
        PlacesRequestFailed::class,
        fn (PlacesRequestFailed $event): bool => str_contains($event->message, 'timed out')
            && ! str_contains($event->message, 'SECRETKEY'),
    );
})->with(['geocode', 'geocodeAddress']);

it('redacts any credential query parameter, not only the configured key', function () {
    Http::fake(fn () => throw new ConnectionException(
        'cURL error 6: Could not resolve host for https://proxy.test/geocode/json?address=x&key=SomeOtherKey9876&signature=SIGNATUREVALUE',
    ));

    $exception = failingCall(fn () => places()->geocodeAddress('Bratislava'));

    expect($exception->getMessage())
        ->not->toContain('SomeOtherKey')
        ->not->toContain('SIGNATUREV')
        ->toContain('address=x');
});

it('redacts a key that google echoes back in an error body', function () {
    Event::fake([PlacesRequestFailed::class]);
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
            'status' => 'REQUEST_DENIED',
            'error_message' => 'The provided API key '.SECRET_KEY.' is invalid.',
        ]),
    ]);

    $exception = failingCall(fn () => places()->geocodeAddress('Bratislava'));

    expect($exception->getMessage())->not->toContain('SECRETKEY')
        ->and($exception->googleErrorMessage())->toContain('1234')->not->toContain('SECRETKEY');

    Event::assertDispatched(
        PlacesRequestFailed::class,
        fn (PlacesRequestFailed $event): bool => $event->message !== '' && ! str_contains($event->message, 'SECRETKEY'),
    );
});

it('redacts a hand-built event and its log line', function () {
    config()->set('google-places.logging.enabled', true);

    $event = new PlacesRequestFailed('geocode', 0, null, 'failed for https://maps.test/geocode/json?key='.SECRET_KEY);

    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('warning')->once()->withArgs(
        fn (string $message, array $context): bool => ! str_contains((string) json_encode($context), 'SECRETKEY'),
    );

    (new LogPlacesActivity)->handleRequestFailed($event);

    expect($event->message)->not->toContain('SECRETKEY')->toContain('key=');
});
