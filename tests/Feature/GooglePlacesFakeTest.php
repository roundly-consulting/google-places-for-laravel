<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ApiCheckResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Distance;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceMatrix;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Geometry;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixElement;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\PhotoQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;
use RoundlyConsulting\GooglePlaces\Testing\GooglePlacesFake;

it('swaps the bound client for a fake', function () {
    $fake = GooglePlaces::fake();

    expect($fake)->toBeInstanceOf(GooglePlacesFake::class)
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

it('intercepts photo bytes and urls requested through the handle', function () {
    Http::preventStrayRequests();
    $fake = GooglePlaces::fake()->withPhoto('JPEG-BYTES', 'https://lh3.googleusercontent.com/seeded');

    $photo = GooglePlaces::photo('places/p1/photos/a', 400, 300);

    expect($photo->contents())->toBe('JPEG-BYTES')
        ->and($photo->url())->toBe('https://lh3.googleusercontent.com/seeded');

    $fake->assertPhotoRequested('places/p1/photos/a');
    $fake->assertPhotoRequested(fn (PhotoQuery $query): bool => $query->maxWidth === 400 && $query->contents === false);
    $fake->assertPhotoRequested(fn (PhotoQuery $query): bool => $query->maxHeight === 300 && $query->contents);
});

it('saves the seeded photo bytes to a disk without calling google', function () {
    Http::preventStrayRequests();
    Storage::fake('photos');
    $fake = GooglePlaces::fake()->withPhoto('JPEG-BYTES');

    GooglePlaces::photo('places/p1/photos/a')->save('photos', 'p1.jpg');

    expect(Storage::disk('photos')->get('p1.jpg'))->toBe('JPEG-BYTES');
    $fake->assertPhotoRequested('places/p1/photos/a');
});

it('answers a photo url from the photo name when none is seeded', function () {
    $fake = GooglePlaces::fake();

    expect(GooglePlaces::photoUri('places/p1/photos/a'))->toBe('https://lh3.googleusercontent.com/fake/places/p1/photos/a')
        ->and(GooglePlaces::photoContents('places/p1/photos/a'))->toBe('');
});

it('fails assertPhotoRequested when no matching photo was requested', function () {
    $fake = GooglePlaces::fake();

    expect(fn () => $fake->assertPhotoRequested())->toThrow(AssertionFailedError::class);

    GooglePlaces::photoContents('places/p1/photos/a');

    expect(fn () => $fake->assertPhotoRequested('places/other/photos/b'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingRequested())->toThrow(AssertionFailedError::class);
});

it('records check calls and reports every api healthy by default', function () {
    $fake = GooglePlaces::fake();

    expect(fn () => $fake->assertChecked())->toThrow(AssertionFailedError::class);

    $results = GooglePlaces::check();

    expect($results)->toHaveCount(3)
        ->and(array_filter($results, fn (ApiCheckResult $r): bool => ! $r->ok))->toBe([]);

    $fake->assertChecked();
    expect(fn () => $fake->assertNothingRequested())->toThrow(AssertionFailedError::class);
});

it('drives the check command from the fake', function () {
    Http::preventStrayRequests();
    $fake = GooglePlaces::fake()->withCheck([
        new ApiCheckResult('Places API (New)', true, 'Reachable and authorized.'),
        new ApiCheckResult('Routes API', false, '403 PERMISSION_DENIED'),
    ]);

    $this->artisan('google-places:check')
        ->expectsOutputToContain('PERMISSION_DENIED')
        ->assertExitCode(1);

    $fake->assertChecked();
});

it('hands the fake to constructor-injected clients and binds handles to it', function () {
    $fake = GooglePlaces::fake()->withDetails(new Place('Café'));

    $injected = app(PlacesClient::class);

    expect($injected)->toBe($fake)
        ->and($injected->session()->details('p1'))->name->toBe('Café');

    $fake->assertDetailsRequested(fn (DetailsQuery $query): bool => $query->place === 'p1' && $query->sessionToken !== null);
});
