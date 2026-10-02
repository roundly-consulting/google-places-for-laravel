<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;
use RoundlyConsulting\Geolocation\DataTransferObjects\GeolocationQuery;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location as ResolvedLocation;
use RoundlyConsulting\Geolocation\Enum\GeolocationType;
use RoundlyConsulting\Geolocation\Events\LocationResolutionFailed;
use RoundlyConsulting\Geolocation\Events\LocationResolved;
use RoundlyConsulting\Geolocation\Exceptions\ProviderUnavailableException;
use RoundlyConsulting\Geolocation\Exceptions\UnknownProviderException;
use RoundlyConsulting\Geolocation\Facades\Geolocation;
use RoundlyConsulting\Geolocation\GeolocationProvider;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\Exceptions\RateLimitExceededException;
use RoundlyConsulting\GooglePlaces\Testing\GooglePlacesFake;

function geocodingResult(): ReverseGeocodingResult
{
    return ReverseGeocodingResult::fromResponse([
        'formatted_address' => '1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA',
        'place_id' => 'geo-1',
        'geometry' => [
            'location' => ['lat' => 37.4224, 'lng' => -122.0841],
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
            ['long_name' => 'California', 'short_name' => 'CA', 'types' => ['administrative_area_level_1']],
            ['long_name' => '94043', 'short_name' => '94043', 'types' => ['postal_code']],
            ['long_name' => 'United States', 'short_name' => 'US', 'types' => ['country']],
        ],
    ]);
}

function fakePlaces(): GooglePlacesFake
{
    $fake = new GooglePlacesFake;
    app()->instance(PlacesClient::class, $fake);

    return $fake;
}

it('registers the google_places geolocation driver', function () {
    fakePlaces();

    expect(fn () => Geolocation::provider('google_places')->locateIp('8.8.8.8'))
        ->not->toThrow(UnknownProviderException::class);
});

it('forward-geocodes an address query into a geolocation Location', function () {
    fakePlaces()->withGeocodeAddress([geocodingResult()]);

    $location = Geolocation::provider('google_places')->locateAddress('1600 Amphitheatre Pkwy');

    expect($location)->toBeInstanceOf(ResolvedLocation::class)
        ->humanReadable->toBe('1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA')
        ->street->toBe('1600 Amphitheatre Parkway')
        ->city->toBe('Mountain View')
        ->region->toBe('California')
        ->postalCode->toBe('94043')
        ->countryIsoCode->toBe('US')
        ->latitude->toBe(37.4224)
        ->longitude->toBe(-122.0841)
        ->timezone->toBe('')
        ->and($location->type)->toBe(GeolocationType::Geolocation);
});

it('reverse-geocodes a coordinate query through the places client', function () {
    $fake = fakePlaces()->withGeocode([geocodingResult()]);

    $location = Geolocation::provider('google_places')
        ->locateCoordinates(new Coordinates(37.4224, -122.0841));

    expect($location)->toBeInstanceOf(ResolvedLocation::class)
        ->city->toBe('Mountain View');

    $fake->assertGeocoded();
});

it('returns null when the places client yields no result', function () {
    fakePlaces()->withGeocodeAddress([]);

    expect(Geolocation::provider('google_places')->locateAddress('nowhere'))->toBeNull();
});

it('returns null for an ip-only query without calling the places client', function () {
    $fake = fakePlaces();

    $location = Geolocation::provider('google_places')->locate(GeolocationQuery::forIp('8.8.8.8'));

    expect($location)->toBeNull();
    $fake->assertNothingRequested();
});

it('resolves through google_places when it is the only pipeline provider', function () {
    Event::fake([LocationResolved::class]);
    config()->set('geolocation.pipeline', ['google_places']);
    fakePlaces()->withGeocodeAddress([geocodingResult()]);

    $location = Geolocation::locateAddress('1600 Amphitheatre Pkwy');

    expect($location)->toBeInstanceOf(ResolvedLocation::class);

    Event::assertDispatched(
        LocationResolved::class,
        fn (LocationResolved $event): bool => $event->provider === 'google_places',
    );
});

/**
 * Put google_places first and a stub that always answers behind it, as a host's
 * `pipeline => ['google_places', 'default']` would.
 */
function pipelineWithFallback(): void
{
    Geolocation::extend('fallback', fn (): GeolocationProvider => new class implements GeolocationProvider
    {
        public function locate(GeolocationQuery $query): ResolvedLocation
        {
            return new ResolvedLocation('Fallback', '', 'Fallback City', 'SK', 48.1, 17.1, GeolocationType::Default);
        }
    });

    config()->set('geolocation.pipeline', ['google_places', 'fallback']);
    config()->set('geolocation.cache.enabled', false);
}

it('falls through to the next provider when google answers with an error', function (array $body) {
    pipelineWithFallback();
    Http::preventStrayRequests();
    Http::fake(['maps.googleapis.com/maps/api/geocode/json*' => Http::response($body)]);

    expect(Geolocation::locateAddress('Bratislava')?->city)->toBe('Fallback City')
        ->and(Geolocation::locateCoordinates(new Coordinates(48.1486, 17.1077))?->city)->toBe('Fallback City');
})->with([
    'over query limit' => [['status' => 'OVER_QUERY_LIMIT', 'error_message' => 'You have exceeded your daily request quota.']],
    'request denied' => [['status' => 'REQUEST_DENIED', 'error_message' => 'The provided API key is invalid.']],
    'invalid request' => [['status' => 'INVALID_REQUEST']],
]);

it('falls through to the next provider when no api key is configured', function () {
    pipelineWithFallback();
    config()->set('google-places.key', null);
    Http::preventStrayRequests();

    expect(Geolocation::locateAddress('Bratislava')?->city)->toBe('Fallback City');
});

it('falls through to the next provider on a client-side rate-limit fail-fast', function () {
    pipelineWithFallback();

    $places = Mockery::mock(PlacesClient::class);
    $places->shouldReceive('geocodeAddress')->andThrow(RateLimitExceededException::for('geocoding', 3));
    app()->instance(PlacesClient::class, $places);

    expect(Geolocation::locateAddress('Bratislava')?->city)->toBe('Fallback City');
});

it('reports an unreachable google as an unavailable provider and moves on', function () {
    Event::fake([LocationResolutionFailed::class]);
    config()->set('geolocation.pipeline', ['google_places']);
    config()->set('geolocation.cache.enabled', false);
    config()->set('google-places.http.retries', 0);
    Http::fake(fn (Request $request) => throw new ConnectionException("cURL error 7: refused for {$request->url()}"));

    expect(Geolocation::locateAddress('Bratislava'))->toBeNull();

    Event::assertDispatched(
        LocationResolutionFailed::class,
        fn (LocationResolutionFailed $event): bool => $event->provider === 'google_places'
            && $event->error instanceof ProviderUnavailableException
            && str_contains($event->error->getMessage(), 'refused')
            && ! str_contains($event->error->getMessage(), 'GoogleApiKey'),
    );
});
