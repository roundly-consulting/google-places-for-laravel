<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;
use RoundlyConsulting\Geolocation\DataTransferObjects\GeolocationQuery;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location as ResolvedLocation;
use RoundlyConsulting\Geolocation\Enum\GeolocationType;
use RoundlyConsulting\Geolocation\Events\LocationResolved;
use RoundlyConsulting\Geolocation\Exceptions\UnknownProviderException;
use RoundlyConsulting\Geolocation\Facades\Geolocation;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
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
