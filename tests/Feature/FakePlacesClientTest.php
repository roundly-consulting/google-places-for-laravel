<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceMatrix;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Geometry;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixElement;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;
use RoundlyConsulting\GooglePlaces\Testing\FakePlacesClient;

it('swaps the bound client for a fake', function () {
    $fake = GooglePlaces::fake();

    expect($fake)->toBeInstanceOf(FakePlacesClient::class)
        ->and(app(PlacesClient::class))->toBe($fake);
});

it('queues and records autocomplete', function () {
    $fake = GooglePlaces::fake();
    $fake->withAutocomplete([new AutocompletePrediction('Café', 'p1', ['cafe'])]);

    $result = GooglePlaces::autocomplete('Café');

    expect($result->first())->placeId->toBe('p1');

    $fake->assertAutocompleted(fn (AutocompleteQuery $query): bool => $query->input === 'Café');
    $fake->assertNothingGeocoded();
});

it('queues and records details', function () {
    $fake = GooglePlaces::fake();
    $fake->withDetails(new Place('Café Roma'));

    expect(GooglePlaces::details('p1'))->name->toBe('Café Roma');

    $fake->assertDetailsRequested('p1');
});

it('queues and records geocode', function () {
    $fake = GooglePlaces::fake();
    $fake->withGeocode([new ReverseGeocodingResult('Somewhere', 'g1', Geometry::fromResponse([
        'location' => ['lat' => 1, 'lng' => 2],
        'viewport' => ['northeast' => ['lat' => 3, 'lng' => 4], 'southwest' => ['lat' => 5, 'lng' => 6]],
    ]), [])]);

    expect(GooglePlaces::geocode(new Location(1, 2)))->toHaveCount(1);

    $fake->assertGeocoded();
});

it('queues and records text and nearby search and find place', function () {
    $fake = GooglePlaces::fake();
    $fake->withTextSearch([new Place('Pizza')]);
    $fake->withNearbySearch([new Place('Nearby')]);
    $fake->withFindPlace(new Place('Found'));

    expect(GooglePlaces::textSearch('pizza')->first())->name->toBe('Pizza')
        ->and(GooglePlaces::nearbySearch(new NearbySearchQuery(new Location(1, 2)))->first())->name->toBe('Nearby')
        ->and(GooglePlaces::findPlace('Found'))->name->toBe('Found');

    $fake->assertTextSearched();
    $fake->assertNearbySearched();
    $fake->assertFindPlaceRequested(fn (string $text): bool => $text === 'Found');
});

it('queues and records distance', function () {
    $fake = GooglePlaces::fake();
    $fake->withDistance(new Distance('10 km', 10000, '12 mins', 720, TravelMode::Driving));

    expect(GooglePlaces::distance(new DistanceQuery(new Location(1, 2), new Location(3, 4))))
        ->distanceInMeters->toBe(10000);

    $fake->assertDistanceRequested();
});

it('returns safe defaults for unset queues', function () {
    $fake = GooglePlaces::fake();

    expect(GooglePlaces::autocomplete('x'))->toBeEmpty()
        ->and(GooglePlaces::details('x'))->toBeNull()
        ->and(GooglePlaces::distance(new DistanceQuery(new Location(1, 2), new Location(3, 4))))->toBeInstanceOf(Distance::class)
        ->and($fake->photoUrl('places/x/photos/y'))->toContain('/media?');
});

it('asserts nothing requested', function () {
    $fake = GooglePlaces::fake();

    $fake->assertNothingRequested();
    $fake->assertNothingAutocompleted();
});

it('records a geocode lat/lng pair shorthand', function () {
    $fake = GooglePlaces::fake();

    GooglePlaces::geocode(48.1, 17.1);

    $fake->assertGeocoded(fn ($query): bool => $query->location->latitude === 48.1);
});

it('fails an assertion when a matching call was not made', function () {
    $fake = GooglePlaces::fake();

    expect(fn () => $fake->assertAutocompleted())->toThrow(AssertionFailedError::class);
});

it('returns a queued photo url', function () {
    $fake = GooglePlaces::fake();
    $fake->withPhotoUrl('https://example.com/photo.jpg');

    expect(GooglePlaces::photoUrl('places/x/photos/y'))->toBe('https://example.com/photo.jpg');
});

it('queues and records forward geocoding', function () {
    $fake = GooglePlaces::fake();
    $fake->withGeocodeAddress([new ReverseGeocodingResult('1 Main St', 'g1', Geometry::fromResponse([
        'location' => ['lat' => 1, 'lng' => 2],
        'viewport' => ['northeast' => ['lat' => 3, 'lng' => 4], 'southwest' => ['lat' => 5, 'lng' => 6]],
    ]), [])]);

    expect(GooglePlaces::geocodeAddress('1 Main St')->first())->placeId->toBe('g1');

    $fake->assertAddressGeocoded('1 Main St');
    $fake->assertAddressGeocoded(fn (GeocodingQuery $query): bool => $query->address === '1 Main St');
});

it('queues and records a distance matrix', function () {
    $fake = GooglePlaces::fake();
    $fake->withMatrix(new DistanceMatrix([
        new MatrixElement(0, 0, 'ROUTE_EXISTS', new Distance('10 km', 10000, '10m', 600)),
    ], 1, 1));

    $matrix = GooglePlaces::matrix([new Location(1, 1)], [new Location(2, 2)])->driving();

    expect($matrix->for(0, 0)?->distance?->distanceInMeters)->toBe(10000);

    $fake->assertMatrixComputed(fn ($query): bool => count($query->origins) === 1);
});

it('returns a default empty matrix when none is queued', function () {
    $fake = GooglePlaces::fake();

    $matrix = GooglePlaces::matrix([new Location(1, 1)], [new Location(2, 2)])->walking();

    expect($matrix->elements)->toBe([])
        ->and($matrix->originCount)->toBe(1);
});

it('paginates over queued search results', function () {
    $fake = GooglePlaces::fake();
    $fake->withTextSearch([new Place('A'), new Place('B')]);
    $fake->withNearbySearch([new Place('N')]);

    expect(GooglePlaces::textSearchPaginated('x')->all())->toHaveCount(2)
        ->and(GooglePlaces::textSearchPaginated('x')->cursor()->first())->name->toBe('A')
        ->and(GooglePlaces::nearbySearchPaginated(new NearbySearchQuery(new Location(1, 2)))->all())->toHaveCount(1);

    $fake->assertTextSearched();
    $fake->assertNearbySearched();
});
