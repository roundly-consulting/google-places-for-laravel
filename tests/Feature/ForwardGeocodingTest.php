<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

beforeEach(fn () => Http::preventStrayRequests());

function forwardGeocodeResponse(): array
{
    return [
        'status' => 'OK',
        'results' => [
            [
                'formatted_address' => '1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA',
                'place_id' => 'geo-fwd-1',
                'geometry' => [
                    'location' => ['lat' => 37.42, 'lng' => -122.08],
                    'viewport' => [
                        'northeast' => ['lat' => 37.43, 'lng' => -122.07],
                        'southwest' => ['lat' => 37.41, 'lng' => -122.09],
                    ],
                ],
                'types' => ['street_address'],
                'address_components' => [
                    ['long_name' => '1600', 'short_name' => '1600', 'types' => ['street_number']],
                    ['long_name' => 'Amphitheatre Parkway', 'short_name' => 'Amphitheatre Pkwy', 'types' => ['route']],
                    ['long_name' => 'Mountain View', 'short_name' => 'Mountain View', 'types' => ['locality']],
                    ['long_name' => 'United States', 'short_name' => 'US', 'types' => ['country']],
                ],
            ],
        ],
    ];
}

it('forward geocodes an address string', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(forwardGeocodeResponse()),
    ]);

    $results = places()->geocodeAddress('1600 Amphitheatre Pkwy');

    expect($results->first())
        ->toBeInstanceOf(ReverseGeocodingResult::class)
        ->address->toBe('1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA')
        ->placeId->toBe('geo-fwd-1')
        ->and($results->first()->geometry->location->latitude)->toBe(37.42);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'address=')
        && str_contains($request->url(), 'key=GoogleApiKey')
        && ! $request->hasHeader('X-Goog-Api-Key'));
});

it('forward geocodes a full query with region and components', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(forwardGeocodeResponse()),
    ]);

    $query = (new GeocodingQuery('Parliament'))
        ->inLanguage('sk')
        ->inRegion('sk')
        ->filterBy('country:SK');

    places()->geocodeAddress($query);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'region=sk')
        && str_contains($request->url(), 'components=country%3ASK')
        && str_contains($request->url(), 'language=sk'));
});

it('returns an empty collection on zero results', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []]),
    ]);

    expect(places()->geocodeAddress('nowhere at all'))->isEmpty()->toBeTrue();
});

it('throws when forward geocoding is denied', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'REQUEST_DENIED', 'error_message' => 'no'], 200),
    ]);

    places()->geocodeAddress('boom');
})->throws(PlacesException::class);
