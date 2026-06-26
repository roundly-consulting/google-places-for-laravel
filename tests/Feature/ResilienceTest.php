<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

beforeEach(fn () => Http::preventStrayRequests());

it('retries a connection failure then succeeds', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts < 2) {
            throw new ConnectionException('timeout');
        }

        return Http::response(['places' => []]);
    });

    config()->set('google-places.http.retries', 2);

    $result = places()->textSearch('coffee');

    expect($result->isEmpty())->toBeTrue()
        ->and($attempts)->toBe(2);
});

it('converts an exhausted connection failure into a PlacesException', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    config()->set('google-places.http.retries', 1);

    places()->textSearch('coffee');
})->throws(PlacesException::class, 'Could not reach the Google Places API');

it('makes a single attempt when retries are disabled', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('timeout');
    });

    config()->set('google-places.http.retries', 0);

    try {
        places()->textSearch('coffee');
    } catch (PlacesException) {
        // expected
    }

    expect($attempts)->toBe(1);
});
