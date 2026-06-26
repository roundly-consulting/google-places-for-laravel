<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

function httpResponse(array|string $body, int $status = 200): Response
{
    return new Response(new GuzzleHttp\Psr7\Response(
        $status,
        ['Content-Type' => 'application/json'],
        is_string($body) ? $body : (string) json_encode($body),
    ));
}

it('parses the Places API (New) error body', function () {
    $exception = PlacesException::fromResponse(httpResponse([
        'error' => ['status' => 'PERMISSION_DENIED', 'message' => 'denied'],
    ], 403));

    expect($exception)
        ->toBeInstanceOf(PlacesException::class)
        ->googleStatus()->toBe('PERMISSION_DENIED')
        ->googleErrorMessage()->toBe('denied')
        ->getCode()->toBe(403)
        ->and($exception->isDenied())->toBeTrue()
        ->and($exception->isRateLimited())->toBeFalse();
});

it('parses the Geocoding API error body', function () {
    $exception = PlacesException::fromResponse(httpResponse([
        'status' => 'OVER_QUERY_LIMIT',
        'error_message' => 'slow down',
    ], 200));

    expect($exception->googleStatus())->toBe('OVER_QUERY_LIMIT')
        ->and($exception->googleErrorMessage())->toBe('slow down')
        ->and($exception->isRateLimited())->toBeTrue();
});

it('recognises invalid-request statuses from both APIs', function () {
    expect(PlacesException::fromResponse(httpResponse(['error' => ['status' => 'INVALID_ARGUMENT']], 400))->isInvalidRequest())->toBeTrue()
        ->and(PlacesException::fromResponse(httpResponse(['status' => 'INVALID_REQUEST'], 200))->isInvalidRequest())->toBeTrue()
        ->and(PlacesException::fromResponse(httpResponse(['status' => 'REQUEST_DENIED'], 200))->isDenied())->toBeTrue();
});

it('builds a missing-key exception without leaking secrets', function () {
    $exception = PlacesException::missingApiKey();

    expect($exception->getMessage())->toContain('GOOGLE_PLACES_API_KEY')
        ->and($exception->googleStatus())->toBeNull();
});

it('builds a connection-failed exception', function () {
    $exception = PlacesException::connectionFailed(new ConnectionException('timeout'));

    expect($exception->getMessage())->toContain('Could not reach');
});

it('builds validation exceptions', function () {
    expect(PlacesException::tooManyPrimaryTypes()->getMessage())->toContain('5')
        ->and(PlacesException::tooManyRegionCodes()->getMessage())->toContain('15')
        ->and(PlacesException::invalidRadius(0, 50000)->getMessage())->toContain('50000')
        ->and(PlacesException::routeNotFound(0, 1, null)->getMessage())->toContain('UNKNOWN');
});
