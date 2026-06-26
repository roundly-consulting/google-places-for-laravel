<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AddressComponent;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Geometry;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MultipleLocations;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\OpeningHourPeriod;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingResult;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Roundtrip;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Places;

beforeEach(fn () => Http::preventStrayRequests());

it('returns photo url', function () {
    $places = new Places;

    $url = $places->photoUrl('hash', 100, 150);

    expect($url)->toBe('https://maps.googleapis.com/maps/api/place/photo?photo_reference=hash&maxwidth=100&maxheight=150&key=GoogleApiKey');
});

it('returns photo url with different base url when defined in config', function () {
    $places = new Places;

    config()->set('google-places.base_url', 'http://localhost/api');

    $url = $places->photoUrl('hash', 100, 150);

    expect($url)->toBe('http://localhost/api/place/photo?photo_reference=hash&maxwidth=100&maxheight=150&key=GoogleApiKey');

    config()->offsetUnset('google-places.base_url');
});

it('throws exception when autocomplete fails', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/place/autocomplete/json?*' => Http::response(body: 'Something happened.',
            status: 500),
    ]);

    $places->autocomplete(new AutocompleteQuery);
})->throws(PlacesException::class, 'Places API Error occured. Response: Something happened.');

it('returns empty collection when we get no results from google api autocomplete', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/place/autocomplete/json?*' => Http::response(body: ['status' => 'ZERO_RESULTS']),
    ]);

    $results = $places->autocomplete(new AutocompleteQuery);

    expect($results)->toBeInstanceOf(Collection::class)
        ->isEmpty()->toBeTrue();
});

it('returns collection of AutocompletePrediction from google places autocomplete', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/place/autocomplete/json?*' => Http::response(body: [
            'status' => 'OK',
            'predictions' => [
                $raw = [
                    'description' => 'Something',
                    'place_id' => '123',
                    'reference' => '456',
                    'types' => ['one', 'two'],
                ],
            ],
        ]),
    ]);

    $results = $places->autocomplete(new AutocompleteQuery);

    expect($results)->toBeInstanceOf(Collection::class)
        ->isEmpty()->toBeFalse()
        ->count()->toBe(1)
        ->and($results->first())
        ->toBeInstanceOf(AutocompletePrediction::class)
        ->description->toBe('Something')
        ->placeId->toBe('123')
        ->reference->toBe('456')
        ->types->toBe(['one', 'two'])
        ->raw->toBe($raw);
});

it('returns null when we get no results from google api place details', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/place/details/json?key=GoogleApiKey&place_id=hash&fields=&language=en' => Http::response(body: ['status' => 'ZERO_RESULTS']),
        'maps.googleapis.com/maps/api/place/details/json?key=GoogleApiKey&place_id=hashTwo&fields=&language=en' => Http::response(body: ['status' => 'NOT_FOUND']),
    ]);

    $result = $places->details(new DetailsQuery('hash', []));

    expect($result)->toBeNull();

    $result = $places->details(new DetailsQuery('hashTwo', []));

    expect($result)->toBeNull();
});

it('returns place details from google api places details', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/place/details/json?key=GoogleApiKey&place_id=hash&fields=address_components%2Cformatted_address%2Cname%2Cgeometry%2Ctype%2Cphoto&language=en' => Http::response(body: [
            'status' => 'OK',
            'name' => 'Somewhere',
            'types' => ['home'],
            'geometry' => [
                'location' => ['lat' => 1, 'lng' => 2],
                'viewport' => [
                    'northeast' => ['lat' => 3, 'lng' => 4],
                    'southwest' => ['lat' => 5, 'lng' => 6],
                ],
            ],
            'opening_hours' => [
                'open_now' => true,
                'periods' => [
                    [
                        'open' => ['day' => 1, 'time' => '10:00'],
                        'close' => ['day' => 1, 'time' => '17:00'],
                    ],
                ],
            ],
        ]),
    ]);

    $result = $places->details(new DetailsQuery('hash'));

    expect($result)
        ->toBeInstanceOf(Place::class)
        ->name->toBe('Somewhere')
        ->types->toBe(['home'])
        ->geometry->location->toBeInstanceOf(Location::class)
        ->geometry->location->toRequest()->toBe('1,2')
        ->geometry->viewportNorthEast->toBeInstanceOf(Location::class)
        ->geometry->viewportNorthEast->toRequest()->toBe('3,4')
        ->geometry->viewportSouthWest->toBeInstanceOf(Location::class)
        ->geometry->viewportSouthWest->toRequest()->toBe('5,6')
        ->openingHours->isOpen->toBeTrue()
        ->openingHours->periods->toHaveCount(1)
        ->and($result->openingHours->periods[0])
        ->toBeInstanceOf(OpeningHourPeriod::class)
        ->day->toBe(1)
        ->from->toBe('10:00')
        ->to->toBe('17:00');
});

it('it throws exception when request to google places details fails', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/place/details/json?key=GoogleApiKey&place_id=hash&fields=address_components%2Cformatted_address%2Cname%2Cgeometry%2Ctype%2Cphoto&language=en' => Http::response(body: [
            'status' => 'UNKNOWN ERROR',
        ], status: 500),
    ]);

    $places->details(new DetailsQuery('hash'));
})->throws(PlacesException::class, 'Places API Error occured. Response: {"status":"UNKNOWN ERROR"}');

it('it throws exception when request to google reverse geocoding fails', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json?key=GoogleApiKey&latlng=1%2C2&language=en' => Http::response(body: [
            'status' => 'UNKNOWN ERROR',
        ], status: 500),
    ]);

    $places->geocode(new ReverseGeocodingQuery(new Location(1, 2)));
})->throws(PlacesException::class, 'Places API Error occured. Response: {"status":"UNKNOWN ERROR"}');

it('returns null when we get no results from google api reverse geocode', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json?key=GoogleApiKey&latlng=1%2C2&language=en' => Http::response(body: ['status' => 'ZERO_RESULTS']),
    ]);

    $result = $places->geocode(new ReverseGeocodingQuery(new Location(1, 2)));

    expect($result)->toBeInstanceOf(Collection::class)->isEmpty()->toBeTrue();
});

it('returns collection of reverse geocoding results', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/geocode/json?key=GoogleApiKey&latlng=1%2C2&language=en' => Http::response(body: [
            'status' => 'OK',
            'results' => [
                [
                    'formatted_address' => 'Somewhere',
                    'place_id' => 'f07575ec-4fcc-4b9e-a5f4-1faa7d59f7f3',
                    'geometry' => [
                        'location' => ['lat' => 1, 'lng' => 2],
                        'viewport' => [
                            'northeast' => ['lat' => 3, 'lng' => 4],
                            'southwest' => ['lat' => 5, 'lng' => 6],
                        ],
                    ],
                    'types' => ['one', 'two'],
                    'address_components' => [
                        [
                            'long_name' => 'Very long name',
                            'short_name' => 'Short name',
                            'types' => ['one', 'two'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $result = $places->geocode(new ReverseGeocodingQuery(new Location(1, 2)));

    expect($result)->toBeInstanceOf(Collection::class)->isEmpty()->toBeFalse();

    $result = $result->first();

    expect($result)
        ->toBeInstanceOf(ReverseGeocodingResult::class)
        ->address->toBe('Somewhere')
        ->placeId->toBe('f07575ec-4fcc-4b9e-a5f4-1faa7d59f7f3')
        ->types->toBe(['one', 'two'])
        ->and($result->geometry)
        ->toBeInstanceOf(Geometry::class)
        ->location->toBeInstanceOf(Location::class)
        ->location->toRequest()->toBe('1,2')
        ->viewportNorthEast->toBeInstanceOf(Location::class)
        ->viewportNorthEast->toRequest()->toBe('3,4')
        ->viewportSouthWest->toBeInstanceOf(Location::class)
        ->viewportSouthWest->toRequest()->toBe('5,6')
        ->and($result->components)
        ->toHaveCount(1)
        ->and($result->components[0])
        ->toBeInstanceOf(AddressComponent::class)
        ->longName->toBe('Very long name')
        ->shortName->toBe('Short name')
        ->types->toBe(['one', 'two']);
});

it('it throws exception when request to get distance from google fails', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/distancematrix/json?key=GoogleApiKey&destinations=3%2C4&origins=1%2C2&mode=driving&language=en' => Http::response(body: [
            'status' => 'UNKNOWN ERROR',
        ], status: 500),
    ]);

    $places->distance(new DistanceQuery(
        new Location(1, 2),
        new Location(3, 4)
    ));
})->throws(PlacesException::class, 'Places API Error occured. Response: {"status":"UNKNOWN ERROR"}');

it('returns distance from google api', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/distancematrix/json?key=GoogleApiKey&destinations=3%2C4&origins=1%2C2&mode=walking&language=en' => Http::response(body: [
            'status' => 'OK',
            'rows' => [
                [
                    'elements' => [
                        [
                            'distance' => ['text' => '10km', 'value' => 10000],
                            'duration' => ['text' => '10min', 'value' => 600],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $distance = $places->distance(new DistanceQuery(
        new Location(1, 2),
        new Location(3, 4),
        'walking',
    ));

    expect($distance)
        ->humanReadableDistance->toBe('10km')
        ->distanceInMeters->toBe(10000)
        ->humanReadableDuration->toBe('10m')
        ->durationInSeconds->toBe(600)
        ->type->toBe('walking');
});

it('returns distance from google api using traffic', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/distancematrix/json?key=GoogleApiKey&destinations=3%2C4&origins=1%2C2&mode=walking&language=en&departure_time=1699437600' => Http::response(body: [
            'status' => 'OK',
            'rows' => [
                [
                    'elements' => [
                        [
                            'distance' => ['text' => '10km', 'value' => 10000],
                            'duration_in_traffic' => ['text' => '10min', 'value' => 600],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    Carbon::setTestNow('2023-11-08 10:00:00');

    $distance = $places->distance(new DistanceQuery(
        new Location(1, 2),
        new Location(3, 4),
        'walking',
        now(),
    ));

    expect($distance)
        ->humanReadableDistance->toBe('10km')
        ->distanceInMeters->toBe(10000)
        ->humanReadableDuration->toBe('10m')
        ->durationInSeconds->toBe(600)
        ->type->toBe('walking');
});

it('returns distance from google api for multiple destinations', function () {
    $places = new Places;

    Http::fake([
        'maps.googleapis.com/maps/api/distancematrix/json?key=GoogleApiKey&destinations=3%2C4%7C5%2C6&origins=1%2C2&mode=walking&language=en' => Http::response(body: [
            'status' => 'OK',
            'rows' => [
                [
                    'elements' => [
                        [
                            'distance' => ['text' => '10km', 'value' => 10000],
                            'duration' => ['text' => '10min', 'value' => 600],
                        ],
                        [
                            'distance' => ['text' => '15km', 'value' => 15000],
                            'duration' => ['text' => '15min', 'value' => 900],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $distance = $places->distance(new DistanceQuery(
        new Location(1, 2),
        new MultipleLocations([
            new Location(3, 4),
            new Location(5, 6),
        ]),
        'walking',
    ));

    expect($distance)->toBeInstanceOf(Roundtrip::class)
        ->distances->toBeArray()
        ->distances->toHaveLength(2)
        ->humanReadableDistance->toBe('25km')
        ->distanceInMeters->toBe(25000)
        ->humanReadableDuration->toBe('25m')
        ->durationInSeconds->toBe(1500)
        ->type->toBe('walking');

    expect($distance->distances[0])
        ->humanReadableDistance->toBe('10km')
        ->distanceInMeters->toBe(10000)
        ->humanReadableDuration->toBe('10m')
        ->durationInSeconds->toBe(600)
        ->type->toBe('walking');

    expect($distance->distances[1])
        ->humanReadableDistance->toBe('15km')
        ->distanceInMeters->toBe(15000)
        ->humanReadableDuration->toBe('15m')
        ->durationInSeconds->toBe(900)
        ->type->toBe('walking');
});
