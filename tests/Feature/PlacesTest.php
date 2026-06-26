<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MultipleLocations;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Roundtrip;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

beforeEach(fn () => Http::preventStrayRequests());

it('builds a Places API (New) photo media url', function () {
    $url = places()->photoUrl('places/place-1/photos/abc', 800, 600);

    expect($url)->toBe('https://places.googleapis.com/v1/places/place-1/photos/abc/media?maxWidthPx=800&maxHeightPx=600&key=GoogleApiKey');
});

it('returns place details with header auth and a field mask', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);

    $place = places()->details(new DetailsQuery('place-1'));

    expect($place)
        ->toBeInstanceOf(Place::class)
        ->name->toBe('Somewhere')
        ->id->toBe('place-1')
        ->formattedAddress->toBe('123 Main St')
        ->types->toBe(['home'])
        ->photos->toBe(['places/place-1/photos/abc'])
        ->geometry->location->toRequest()->toBe('1,2')
        ->geometry->viewportNorthEast->toRequest()->toBe('3,4')
        ->geometry->viewportSouthWest->toRequest()->toBe('5,6')
        ->openingHours->isOpen->toBeTrue()
        ->and($place->openingHours->periods[0])
        ->day->toBe(1)
        ->from->toBe('1000')
        ->to->toBe('1730')
        ->openHour->toBe(10)
        ->closeMinute->toBe(30);

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('X-Goog-Api-Key', 'GoogleApiKey')
            && $request->hasHeader('X-Goog-FieldMask')
            && ! str_contains($request->url(), 'key=');
    });
});

it('accepts a place id string shorthand for details', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);

    expect(places()->details('place-1'))->toBeInstanceOf(Place::class);
});

it('sends a field mask that asks for types and photos, never the singular bug values', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);

    places()->details(new DetailsQuery('place-1'));

    Http::assertSent(function (Request $request): bool {
        $mask = explode(',', $request->header('X-Goog-FieldMask')[0]);

        return in_array('types', $mask, true)
            && in_array('photos', $mask, true)
            && ! in_array('type', $mask, true)
            && ! in_array('photo', $mask, true);
    });
});

it('honours a custom details field mask override', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);

    places()->details(new DetailsQuery('place-1', ['types', 'photos']));

    Http::assertSent(fn (Request $request): bool => $request->header('X-Goog-FieldMask')[0] === 'types,photos');
});

it('returns null when place details are not found', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404),
    ]);

    expect(places()->details(new DetailsQuery('missing')))->toBeNull();
});

it('throws when place details fail', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED', 'message' => 'denied']], 403),
    ]);

    places()->details(new DetailsQuery('place-1'));
})->throws(PlacesException::class);

it('maps autocomplete suggestions, skipping query predictions', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [
                [
                    'placePrediction' => [
                        'placeId' => 'p1',
                        'text' => ['text' => 'Café Roma'],
                        'structuredFormat' => [
                            'mainText' => ['text' => 'Café Roma'],
                            'secondaryText' => ['text' => 'Bratislava'],
                        ],
                        'types' => ['cafe'],
                    ],
                ],
                [
                    'queryPrediction' => ['text' => ['text' => 'coffee near me']],
                ],
            ],
        ]),
    ]);

    $results = places()->autocomplete('Café');

    expect($results)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(1)
        ->and($results->first())
        ->toBeInstanceOf(AutocompletePrediction::class)
        ->description->toBe('Café Roma')
        ->placeId->toBe('p1')
        ->types->toBe(['cafe'])
        ->mainText->toBe('Café Roma')
        ->secondaryText->toBe('Bratislava');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request['input'] === 'Café'
        && $request->hasHeader('X-Goog-Api-Key', 'GoogleApiKey'));
});

it('returns an empty collection when autocomplete has no suggestions', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response([]),
    ]);

    expect(places()->autocomplete('nothing'))->toBeInstanceOf(Collection::class)->isEmpty()->toBeTrue();
});

it('throws when autocomplete fails', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response(['error' => ['status' => 'INVALID_ARGUMENT']], 400),
    ]);

    places()->autocomplete('boom');
})->throws(PlacesException::class);

it('reverse geocodes through the geocoding api with a key query param', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
            'status' => 'OK',
            'results' => [
                [
                    'formatted_address' => 'Somewhere',
                    'place_id' => 'geo-1',
                    'geometry' => [
                        'location' => ['lat' => 1, 'lng' => 2],
                        'viewport' => [
                            'northeast' => ['lat' => 3, 'lng' => 4],
                            'southwest' => ['lat' => 5, 'lng' => 6],
                        ],
                    ],
                    'types' => ['street_address'],
                    'address_components' => [
                        ['long_name' => 'Main Street', 'short_name' => 'Main St', 'types' => ['route']],
                    ],
                ],
            ],
        ]),
    ]);

    $results = places()->geocode(48.1486, 17.1077);

    expect($results->first())
        ->toBeInstanceOf(ReverseGeocodingResult::class)
        ->address->toBe('Somewhere')
        ->placeId->toBe('geo-1');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'key=GoogleApiKey')
        && ! $request->hasHeader('X-Goog-Api-Key'));
});

it('throws when reverse geocoding fails', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json*' => Http::response(['status' => 'REQUEST_DENIED', 'error_message' => 'no'], 200),
    ]);

    places()->geocode(new Location(1, 2));
})->throws(PlacesException::class);

it('runs text search and maps places', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => [placeResponse()]]),
    ]);

    $results = places()->textSearch('pizza in bratislava');

    expect($results)->toHaveCount(1)->and($results->first())->toBeInstanceOf(Place::class)->name->toBe('Somewhere');

    Http::assertSent(fn (Request $request): bool => $request['textQuery'] === 'pizza in bratislava'
        && $request->header('X-Goog-FieldMask')[0] === (string) config('google-places.field_masks.search'));
});

it('returns an empty collection for text search with no places', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response([]),
    ]);

    expect(places()->textSearch('nothing'))->isEmpty()->toBeTrue();
});

it('throws when text search fails', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['error' => ['status' => 'INVALID_ARGUMENT']], 400),
    ]);

    places()->textSearch('boom');
})->throws(PlacesException::class);

it('runs nearby search', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchNearby' => Http::response(['places' => [placeResponse()]]),
    ]);

    $results = places()->nearbySearch(new NearbySearchQuery(new Location(1, 2), radius: 1000));

    expect($results)->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => $request['locationRestriction']['circle']['radius'] === 1000);
});

it('finds a single place via text search', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => [placeResponse()]]),
    ]);

    $place = places()->findPlace('Somewhere', new Location(1, 2));

    expect($place)->toBeInstanceOf(Place::class)->name->toBe('Somewhere');

    Http::assertSent(fn (Request $request): bool => $request['pageSize'] === 1
        && isset($request['locationBias']['circle']));
});

it('returns null from find place when nothing matches', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response([]),
    ]);

    expect(places()->findPlace('nothing'))->toBeNull();
});

it('computes a single distance via the routes api', function () {
    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10000, 'duration' => '600s', 'condition' => 'ROUTE_EXISTS'],
        ]),
    ]);

    $distance = places()->distance(new DistanceQuery(new Location(1, 2), new Location(3, 4), TravelMode::Walking));

    expect($distance)
        ->toBeInstanceOf(Distance::class)
        ->distanceInMeters->toBe(10000)
        ->humanReadableDistance->toBe('10 km')
        ->durationInSeconds->toBe(600)
        ->humanReadableDuration->toBe('10m')
        ->type->toBe(TravelMode::Walking);

    Http::assertSent(fn (Request $request): bool => $request['travelMode'] === 'WALK'
        && $request->hasHeader('X-Goog-FieldMask'));
});

it('computes a roundtrip distance for multiple destinations', function () {
    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([
            ['originIndex' => 0, 'destinationIndex' => 1, 'distanceMeters' => 15000, 'duration' => '900s', 'condition' => 'ROUTE_EXISTS'],
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10000, 'duration' => '600s', 'condition' => 'ROUTE_EXISTS'],
        ]),
    ]);

    $trip = places()->distance(new DistanceQuery(
        new Location(1, 2),
        new MultipleLocations([new Location(3, 4), new Location(5, 6)]),
        TravelMode::Driving,
    ));

    expect($trip)
        ->toBeInstanceOf(Roundtrip::class)
        ->distanceInMeters->toBe(25000)
        ->humanReadableDistance->toBe('25 km')
        ->durationInSeconds->toBe(1500)
        ->and($trip->distances[0]->distanceInMeters)->toBe(10000)
        ->and($trip->distances[1]->distanceInMeters)->toBe(15000);
});

it('sends a traffic-aware routing preference and departure time when departing', function () {
    Carbon::setTestNow('2026-06-26 10:00:00');

    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([
            ['originIndex' => 0, 'destinationIndex' => 0, 'distanceMeters' => 10000, 'duration' => '600s', 'condition' => 'ROUTE_EXISTS'],
        ]),
    ]);

    places()->distance(new DistanceQuery(new Location(1, 2), new Location(3, 4), TravelMode::Driving, now()));

    Http::assertSent(fn (Request $request): bool => $request['routingPreference'] === 'TRAFFIC_AWARE'
        && $request['departureTime'] === '2026-06-26T10:00:00Z');

    Carbon::setTestNow();
});

it('throws when a route does not exist', function () {
    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response([
            ['originIndex' => 0, 'destinationIndex' => 0, 'condition' => 'ROUTE_NOT_FOUND'],
        ]),
    ]);

    places()->distance(new DistanceQuery(new Location(1, 2), new Location(3, 4)));
})->throws(PlacesException::class, 'No route exists');

it('throws when the routes response is not a list of elements', function () {
    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response('not json', 200),
    ]);

    places()->distance(new DistanceQuery(new Location(1, 2), new Location(3, 4)));
})->throws(PlacesException::class);

it('throws when the routes request fails', function () {
    Http::fake([
        'routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403),
    ]);

    places()->distance(new DistanceQuery(new Location(1, 2), new Location(3, 4)));
})->throws(PlacesException::class);

it('throws a missing-key exception before making a request', function () {
    config()->set('google-places.key', null);

    places()->details(new DetailsQuery('place-1'));
})->throws(PlacesException::class, 'Google Places API key is missing');

it('applies a location bias on autocomplete', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response([]),
    ]);

    $query = (new AutocompleteQuery('Coffee'))
        ->ofType('cafe')
        ->preferInArea((new LocationDefinition)->circle(new Location(48.1, 17.1), 2000))
        ->inRegions('sk');

    places()->autocomplete($query);

    Http::assertSent(fn (Request $request): bool => $request['includedPrimaryTypes'] === ['cafe']
        && $request['includedRegionCodes'] === ['sk']
        && $request['locationBias']['circle']['radius'] === 2000);
});
