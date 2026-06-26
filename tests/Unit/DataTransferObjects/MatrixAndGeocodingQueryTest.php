<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\SearchPage;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

it('builds a forward geocoding request body', function () {
    $request = (new GeocodingQuery('Main St'))
        ->withAddress('1 Main St')
        ->inLanguage('sk')
        ->inRegion('sk')
        ->filterBy('country:SK', 'postal_code:81101')
        ->toRequest();

    expect($request)->toBe([
        'address' => '1 Main St',
        'language' => 'sk',
        'region' => 'sk',
        'components' => 'country:SK|postal_code:81101',
    ]);
});

it('omits optional geocoding parameters when unset', function () {
    expect((new GeocodingQuery('x'))->toRequest())->toBe(['address' => 'x', 'language' => 'en']);
});

it('builds a matrix routes body with many origins and destinations', function () {
    $query = new MatrixQuery(
        origins: [new Location(1, 1), new Location(2, 2)],
        destinations: [new Location(3, 3)],
    );

    $body = $query->driving()->toRoutesBody();

    expect($body['travelMode'])->toBe('DRIVE')
        ->and($body['origins'])->toHaveCount(2)
        ->and($body['destinations'])->toHaveCount(1)
        ->and($body['origins'][0]['waypoint']['location']['latLng'])->toBe(['latitude' => 1.0, 'longitude' => 1.0]);
});

it('switches matrix travel modes immutably', function () {
    $query = new MatrixQuery([new Location(1, 1)], [new Location(2, 2)]);

    expect($query->walking()->type)->toBe(TravelMode::Walking)
        ->and($query->bicycling()->type)->toBe(TravelMode::Bicycling)
        ->and($query->transit()->type)->toBe(TravelMode::Transit)
        ->and($query->type)->toBe(TravelMode::Driving);
});

it('adds a traffic-aware departure to a driving matrix', function () {
    Carbon::setTestNow('2026-06-26 09:00:00');

    $body = (new MatrixQuery([new Location(1, 1)], [new Location(2, 2)]))
        ->departingAt(now())
        ->toRoutesBody();

    expect($body['departureTime'])->toBe('2026-06-26T09:00:00Z')
        ->and($body['routingPreference'])->toBe('TRAFFIC_AWARE');

    Carbon::setTestNow();
});

it('accepts a travel-mode string for the matrix', function () {
    expect((new MatrixQuery([], [], 'walking'))->type)->toBe(TravelMode::Walking);
});

it('flags whether a search page has more results', function () {
    expect((new SearchPage([], 'token'))->hasMore())->toBeTrue()
        ->and((new SearchPage([], null))->hasMore())->toBeFalse()
        ->and((new SearchPage([], ''))->hasMore())->toBeFalse();
});
