<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

/*
 * The fixtures under tests/Fixtures/errors are Google's answers RECORDED against the live APIs
 * with a deliberately invalid key (2026-09-26) — not shapes written from memory. Two of them
 * are the reason this file exists:
 *
 * - Places API (New) files an invalid key under `INVALID_ARGUMENT`, the same status as a bad
 *   request; only `error.details[].reason` (`API_KEY_INVALID`) says it is the KEY.
 * - The Routes API's computeRouteMatrix streams, so even its error arrives wrapped in a JSON
 *   ARRAY: `[{"error": {…}}]`. Reading `error.status` off that finds nothing at all.
 */
beforeEach(fn () => Http::preventStrayRequests());

/** @return array<mixed> */
function recordedError(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../Fixtures/errors/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
}

function catchPlaces(Closure $call): PlacesException
{
    try {
        $call();
    } catch (PlacesException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a PlacesException.');
}

it('classifies an invalid key on Places API (New) as denied, not as a bad request', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(recordedError('places-api-key-invalid'), 400)]);

    $exception = catchPlaces(fn () => places()->autocomplete('Bratislava'));

    expect($exception->isDenied())->toBeTrue()
        ->and($exception->isInvalidRequest())->toBeFalse()
        ->and($exception->isRateLimited())->toBeFalse()
        ->and($exception->googleStatus())->toBe('INVALID_ARGUMENT')
        ->and($exception->googleReason())->toBe('API_KEY_INVALID')
        ->and($exception->googleErrorMessage())->toBe('API key not valid. Please pass a valid API key.');
});

it('reads the array-wrapped Routes API error and classifies an invalid key as denied', function () {
    Http::fake(['routes.googleapis.com/*' => Http::response(recordedError('routes-api-key-invalid'), 400)]);

    $exception = catchPlaces(fn () => places()->distance(new DistanceQuery(
        from: new Location(48.1486, 17.1077),
        to: new Location(48.2082, 16.3738),
        type: TravelMode::Driving,
    )));

    expect($exception->googleStatus())->toBe('INVALID_ARGUMENT')
        ->and($exception->googleReason())->toBe('API_KEY_INVALID')
        ->and($exception->googleErrorMessage())->toBe('API key not valid. Please pass a valid API key.')
        ->and($exception->isDenied())->toBeTrue()
        ->and($exception->isInvalidRequest())->toBeFalse();
});

it('reports the Routes API status on the failure event too', function () {
    Http::fake(['routes.googleapis.com/*' => Http::response(recordedError('routes-api-key-invalid'), 400)]);

    $events = [];
    app('events')->listen(PlacesRequestFailed::class, function ($event) use (&$events): void {
        $events[] = $event;
    });

    catchPlaces(fn () => places()->distance(new DistanceQuery(
        from: new Location(1, 1),
        to: new Location(2, 2),
        type: TravelMode::Driving,
    )));

    expect($events)->toHaveCount(1)
        ->and($events[0]->googleStatus)->toBe('INVALID_ARGUMENT')
        ->and($events[0]->message)->toBe('API key not valid. Please pass a valid API key.');
});

it('keeps the legacy Geocoding REQUEST_DENIED classified as denied', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response(recordedError('geocoding-request-denied'), 200)]);

    $exception = catchPlaces(fn () => places()->geocodeAddress('Googleplex'));

    expect($exception->isDenied())->toBeTrue()
        ->and($exception->googleStatus())->toBe('REQUEST_DENIED')
        ->and($exception->googleReason())->toBeNull()
        ->and($exception->googleErrorMessage())->toBe('The provided API key is invalid. ');
});

it('classifies a call with no usable identity as denied', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(recordedError('places-unregistered-caller'), 403)]);

    expect(catchPlaces(fn () => places()->autocomplete('x'))->isDenied())->toBeTrue();
});

it('classifies every key and project refusal Google files under a reason as denied', function (string $status, string $reason, int $http) {
    // Google's documented google.rpc.ErrorInfo shape; the status a reason is filed under
    // varies (an expired key is INVALID_ARGUMENT, a blocked one PERMISSION_DENIED).
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => [
        'code' => $http,
        'message' => 'refused',
        'status' => $status,
        'details' => [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => $reason, 'domain' => 'googleapis.com']],
    ]], $http)]);

    $exception = catchPlaces(fn () => places()->autocomplete('x'));

    expect($exception->isDenied())->toBeTrue()
        ->and($exception->isInvalidRequest())->toBeFalse()
        ->and($exception->googleReason())->toBe($reason);
})->with([
    'expired key' => ['INVALID_ARGUMENT', 'API_KEY_EXPIRED', 400],
    'api not allowed for the key' => ['PERMISSION_DENIED', 'API_KEY_SERVICE_BLOCKED', 403],
    'api disabled on the project' => ['PERMISSION_DENIED', 'SERVICE_DISABLED', 403],
    'referrer restriction' => ['PERMISSION_DENIED', 'API_KEY_HTTP_REFERRER_BLOCKED', 403],
    'ip restriction' => ['PERMISSION_DENIED', 'API_KEY_IP_ADDRESS_BLOCKED', 403],
    'billing disabled' => ['PERMISSION_DENIED', 'BILLING_DISABLED', 403],
]);

it('still classifies a genuinely bad request as invalid', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => [
        'code' => 400,
        'message' => 'Invalid JSON payload received.',
        'status' => 'INVALID_ARGUMENT',
        'details' => [['@type' => 'type.googleapis.com/google.rpc.BadRequest', 'fieldViolations' => [['field' => 'input']]]],
    ]], 400)]);

    $exception = catchPlaces(fn () => places()->autocomplete('x'));

    expect($exception->isInvalidRequest())->toBeTrue()
        ->and($exception->isDenied())->toBeFalse()
        ->and($exception->googleReason())->toBeNull();
});

it('classifies an array-wrapped Routes quota error as rate limited', function () {
    Http::fake(['routes.googleapis.com/*' => Http::response([['error' => [
        'code' => 429,
        'message' => 'Quota exceeded.',
        'status' => 'RESOURCE_EXHAUSTED',
    ]]], 429)]);

    $exception = catchPlaces(fn () => places()->distance(new DistanceQuery(
        from: new Location(1, 1),
        to: new Location(2, 2),
        type: TravelMode::Driving,
    )));

    expect($exception->isRateLimited())->toBeTrue()
        ->and($exception->isDenied())->toBeFalse();
});

it('classifies the legacy OVER_DAILY_LIMIT (bad key, billing, cap) as denied', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response([
        'error_message' => 'You have exceeded your daily request quota for this API.',
        'results' => [],
        'status' => 'OVER_DAILY_LIMIT',
    ], 200)]);

    expect(catchPlaces(fn () => places()->geocodeAddress('x'))->isDenied())->toBeTrue();
});
