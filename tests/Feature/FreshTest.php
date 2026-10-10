<?php

declare(strict_types=1);

use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceMatrix;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;
use RoundlyConsulting\HttpClientRateLimits\Facades\RateLimits;

/**
 * A `fresh` query skips the cache read, asks Google, and writes the answer back.
 */
beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('google-places.cache.enabled', true);
});

/**
 * A place resource whose display name tells two answers apart.
 *
 * @return array<string, mixed>
 */
function namedPlace(string $name): array
{
    return array_replace(placeResponse(), ['displayName' => ['text' => $name]]);
}

/**
 * One answer for every cached endpoint.
 */
function fakeEveryCachedEndpoint(): void
{
    $route = [['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 1000, 'duration' => '60s', 'condition' => 'ROUTE_EXISTS']];

    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => [placeResponse()]]),
        'places.googleapis.com/v1/places:searchNearby' => Http::response(['places' => [placeResponse()]]),
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'OK', 'results' => []]),
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response($route),
    ]);
}

/**
 * Every cached endpoint, as a plain call and the same call made fresh.
 *
 * @return array<string, array{0: Closure(): mixed, 1: Closure(): mixed}>
 */
dataset('cached endpoints', fn (): array => [
    'details' => [
        fn () => places()->details(new DetailsQuery('place-1')),
        fn () => places()->details((new DetailsQuery('place-1'))->fresh()),
    ],
    'geocode' => [
        fn () => places()->geocode(new ReverseGeocodingQuery(new Location(1, 2))),
        fn () => places()->geocode((new ReverseGeocodingQuery(new Location(1, 2)))->fresh()),
    ],
    'geocodeAddress' => [
        fn () => places()->geocodeAddress(new GeocodingQuery('1 Main St')),
        fn () => places()->geocodeAddress((new GeocodingQuery('1 Main St'))->fresh()),
    ],
    'textSearch' => [
        fn () => places()->textSearch(new TextSearchQuery('pizza')),
        fn () => places()->textSearch((new TextSearchQuery('pizza'))->fresh()),
    ],
    'nearbySearch' => [
        fn () => places()->nearbySearch(new NearbySearchQuery(new Location(1, 2))),
        fn () => places()->nearbySearch((new NearbySearchQuery(new Location(1, 2)))->fresh()),
    ],
    'distance' => [
        fn () => places()->distance(new DistanceQuery(new Location(1, 1), new Location(2, 2))),
        fn () => places()->distance((new DistanceQuery(new Location(1, 1), new Location(2, 2)))->fresh()),
    ],
    'computeMatrix' => [
        fn () => places()->computeMatrix(new MatrixQuery([new Location(1, 1)], [new Location(2, 2)])),
        fn () => places()->computeMatrix((new MatrixQuery([new Location(1, 1)], [new Location(2, 2)]))->fresh()),
    ],
    'matrix()' => [
        fn () => places()->matrix([new Location(1, 1)], [new Location(2, 2)])->driving(),
        fn () => places()->matrix([new Location(1, 1)], [new Location(2, 2)])->fresh()->driving(),
    ],
]);

it('refreshes a cached place and writes the new answer through', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::sequence()
            ->push(namedPlace('v1'))
            ->push(namedPlace('v2')),
    ]);

    expect(places()->details('place-1')?->name)->toBe('v1')
        ->and(places()->details(new DetailsQuery('place-1', fresh: true))?->name)->toBe('v2');

    Http::assertSentCount(2);

    // Write-through: the next plain read gets the refreshed payload from the cache.
    expect(places()->details('place-1')?->name)->toBe('v2');

    Http::assertSentCount(2);
});

it('sends a request for a fresh query over a warm cache', function (Closure $plain, Closure $fresh) {
    fakeEveryCachedEndpoint();

    $written = [];
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$written): void {
        $written[] = $event->key;
    });

    $plain();
    $plain();

    Http::assertSentCount(1);

    $fresh();

    Http::assertSentCount(2);

    $plain();

    Http::assertSentCount(2);

    // Same cache key, and the same request on the wire: `fresh` never leaves the process.
    [[$warm], [$refresh]] = Http::recorded()->all();

    expect($written)->toHaveCount(2)
        ->and($written[1])->toBe($written[0])
        ->and($refresh->method())->toBe($warm->method())
        ->and($refresh->url())->toBe($warm->url())
        ->and($refresh->body())->toBe($warm->body())
        ->and(strtolower($refresh->url().$refresh->body()))->not->toContain('fresh');
})->with('cached endpoints');

it('remembers a fresh 404 as no such place', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::sequence()
            ->push(namedPlace('v1'))
            ->push([], 404),
    ]);

    expect(places()->details('place-1'))->toBeInstanceOf(Place::class)
        ->and(places()->details((new DetailsQuery('place-1'))->fresh()))->toBeNull()
        ->and(places()->details('place-1'))->toBeNull();

    Http::assertSentCount(2);
});

it('leaves the cached entry alone when a fresh call fails', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::sequence()
            ->push(namedPlace('v1'))
            ->push(['error' => ['code' => 500, 'message' => 'Internal error', 'status' => 'INTERNAL']], 500),
    ]);

    expect(places()->details('place-1')?->name)->toBe('v1');

    expect(fn () => places()->details((new DetailsQuery('place-1'))->fresh()))
        ->toThrow(PlacesException::class);

    expect(places()->details('place-1')?->name)->toBe('v1');

    Http::assertSentCount(2);
});

it('changes nothing when the cache is disabled', function () {
    config()->set('google-places.cache.enabled', false);

    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::sequence()
            ->push(namedPlace('v1'))
            ->push(namedPlace('v2'))
            ->push(namedPlace('v3')),
    ]);

    expect(places()->details('place-1')?->name)->toBe('v1')
        ->and(places()->details((new DetailsQuery('place-1'))->fresh())?->name)->toBe('v2')
        ->and(places()->details('place-1')?->name)->toBe('v3');

    Http::assertSentCount(3);
});

it('counts a fresh call as a real request: an event and a rate-limit slot', function () {
    Event::fake([PlacesResponseReceived::class]);
    $limits = RateLimits::fake();

    Http::fake(['places.googleapis.com/v1/places/*' => Http::response(placeResponse())]);

    places()->details('place-1');
    places()->details('place-1');

    Event::assertDispatchedTimes(PlacesResponseReceived::class, 1);
    expect($limits->allowedCount())->toBe(1);

    places()->details((new DetailsQuery('place-1'))->fresh());

    Event::assertDispatchedTimes(PlacesResponseReceived::class, 2);
    expect($limits->allowedCount())->toBe(2);
    $limits->assertAllowed('google-places:places:app');
});

it('carries fresh through a session details call', function () {
    $fake = GooglePlaces::fake()->withDetails(new Place('Café'));

    GooglePlaces::session()->details((new DetailsQuery('p1'))->fresh());

    $fake->assertDetailsRequested(fn (DetailsQuery $query): bool => $query->fresh && $query->sessionToken !== null);
});

it('carries fresh from the pending matrix into its query', function () {
    $fake = GooglePlaces::fake()->withMatrix(DistanceMatrix::fromRoutesElements([], 1, 1));

    GooglePlaces::matrix([new Location(1, 1)], [new Location(2, 2)])->fresh()->walking();

    $fake->assertMatrixComputed(fn (MatrixQuery $query): bool => $query->fresh);

    $plain = GooglePlaces::fake()->withMatrix(DistanceMatrix::fromRoutesElements([], 1, 1));

    GooglePlaces::matrix([new Location(1, 1)], [new Location(2, 2)])->fresh()->fresh(false)->walking();

    $plain->assertMatrixComputed(fn (MatrixQuery $query): bool => ! $query->fresh);
});

it('asserts on the fresh flag through the fake', function () {
    $fake = GooglePlaces::fake()->withDetails(new Place('Café'));

    GooglePlaces::details(new DetailsQuery('p1'));

    expect(fn () => $fake->assertDetailsRequested(fn (DetailsQuery $query): bool => $query->fresh))
        ->toThrow(AssertionFailedError::class);

    GooglePlaces::details((new DetailsQuery('p1'))->fresh());

    $fake->assertDetailsRequested(fn (DetailsQuery $query): bool => $query->fresh);
});
