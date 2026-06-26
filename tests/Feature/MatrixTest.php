<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceMatrix;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

beforeEach(fn () => Http::preventStrayRequests());

function matrixResponse(): array
{
    return [
        ['originIndex' => 1, 'destinationIndex' => 0, 'distanceMeters' => 20000, 'duration' => '1200s', 'condition' => 'ROUTE_EXISTS'],
        ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10000, 'duration' => '600s', 'condition' => 'ROUTE_EXISTS'],
        ['originIndex' => 0, 'destinationIndex' => 1, 'condition' => 'ROUTE_NOT_FOUND', 'status' => ['code' => 5]],
        ['originIndex' => 1, 'destinationIndex' => 1, 'distanceMeters' => 30000, 'duration' => '1800s', 'condition' => 'ROUTE_EXISTS'],
    ];
}

it('computes a full origins by destinations matrix', function () {
    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response(matrixResponse()),
    ]);

    $matrix = GooglePlaces::matrix(
        [new Location(1, 1), new Location(2, 2)],
        [new Location(3, 3), new Location(4, 4)],
    )->driving();

    expect($matrix)
        ->toBeInstanceOf(DistanceMatrix::class)
        ->originCount->toBe(2)
        ->destinationCount->toBe(2)
        ->type->toBe(TravelMode::Driving);

    expect($matrix->for(0, 0)?->distance?->distanceInMeters)->toBe(10000);
    expect($matrix->for(1, 1)?->distance?->distanceInMeters)->toBe(30000);
    expect($matrix->for(0, 1)?->hasRoute())->toBeFalse();
    expect($matrix->for(0, 1)?->status)->toBe('5');

    expect($matrix->origin(1))->toHaveCount(2)
        ->and($matrix->origin(1)->get(0)?->distance?->distanceInMeters)->toBe(20000);

    expect($matrix->destination(0))->toHaveCount(2);

    Http::assertSent(fn (Request $request): bool => count($request['origins']) === 2
        && count($request['destinations']) === 2
        && $request['travelMode'] === 'DRIVE');
});

it('supports walking mode and a departure time', function () {
    Carbon::setTestNow('2026-06-26 10:00:00');

    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 1000, 'duration' => '600s', 'condition' => 'ROUTE_EXISTS'],
        ]),
    ]);

    $matrix = GooglePlaces::matrix([new Location(1, 1)], [new Location(2, 2)])
        ->departingAt(now())
        ->driving();

    expect($matrix->elements)->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => $request['departureTime'] === '2026-06-26T10:00:00Z'
        && $request['routingPreference'] === 'TRAFFIC_AWARE');

    Carbon::setTestNow();
});

it('supports transit mode', function () {
    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 1000, 'duration' => '600s', 'condition' => 'ROUTE_EXISTS'],
        ]),
    ]);

    $matrix = GooglePlaces::matrix([new Location(1, 1)], [new Location(2, 2)])->transit();

    expect($matrix->type)->toBe(TravelMode::Transit);

    Http::assertSent(fn (Request $request): bool => $request['travelMode'] === 'TRANSIT');
});

it('throws when the matrix request fails', function () {
    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403),
    ]);

    GooglePlaces::matrix([new Location(1, 1)], [new Location(2, 2)])->walking();
})->throws(PlacesException::class);

it('throws when the matrix response is not a list', function () {
    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response('nope', 200),
    ]);

    GooglePlaces::matrix([new Location(1, 1)], [new Location(2, 2)])->bicycling();
})->throws(PlacesException::class);
