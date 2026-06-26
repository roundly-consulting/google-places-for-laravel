<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MultipleLocations;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

it('keeps the autocomplete query immutable while chaining', function () {
    $base = new AutocompleteQuery('Coffee');
    $changed = $base->withInput('Tea')->inLanguage('sk');

    expect($base->input)->toBe('Coffee')
        ->and($base->language)->toBe('en')
        ->and($changed->input)->toBe('Tea')
        ->and($changed->language)->toBe('sk');
});

it('builds an autocomplete body', function () {
    $body = (new AutocompleteQuery('Coffee'))
        ->ofType('cafe')
        ->inRegions('sk')
        ->fromOrigin(new Location(1, 2))
        ->usingSessionToken('tok')
        ->preferInArea((new LocationDefinition)->circle(new Location(1, 2), 1000))
        ->restrictTo((new LocationDefinition)->rectangle(new Location(1, 2), new Location(3, 4)))
        ->toBody();

    expect($body)
        ->toHaveKey('input', 'Coffee')
        ->toHaveKey('languageCode', 'en')
        ->toHaveKey('includedPrimaryTypes', ['cafe'])
        ->toHaveKey('includedRegionCodes', ['sk'])
        ->toHaveKey('sessionToken', 'tok')
        ->and($body['origin'])->toBe(['latitude' => 1.0, 'longitude' => 2.0])
        ->and($body['locationBias']['circle']['radius'])->toBe(1000)
        ->and($body['locationRestriction'])->toHaveKey('rectangle');
});

it('rejects more than five primary types', function () {
    new AutocompleteQuery(includedPrimaryTypes: ['a', 'b', 'c', 'd', 'e', 'f']);
})->throws(PlacesException::class);

it('rejects more than fifteen region codes', function () {
    new AutocompleteQuery(includedRegionCodes: array_fill(0, 16, 'sk'));
})->throws(PlacesException::class);

it('builds the details field mask and query params', function () {
    $query = new DetailsQuery('p1', ['types', 'photos'], 'sk', 'tok');

    expect($query->fieldMask())->toBe('types,photos')
        ->and($query->toRequest())->toBe(['languageCode' => 'sk', 'sessionToken' => 'tok']);

    expect((new DetailsQuery('p1'))->fieldMask())->toBeNull();
});

it('builds a routes body for a single destination', function () {
    $body = (new DistanceQuery(new Location(1, 2), new Location(3, 4), TravelMode::Walking))->toRoutesBody();

    expect($body['travelMode'])->toBe('WALK')
        ->and($body['origins'][0]['waypoint']['location']['latLng'])->toBe(['latitude' => 1.0, 'longitude' => 2.0])
        ->and($body['destinations'])->toHaveCount(1)
        ->and($body)->not->toHaveKey('routingPreference');
});

it('adds a traffic-aware preference for driving departures', function () {
    Carbon::setTestNow('2026-06-26 10:00:00');

    $body = (new DistanceQuery(new Location(1, 2), new Location(3, 4)))->departingAt(now())->toRoutesBody();

    expect($body['routingPreference'])->toBe('TRAFFIC_AWARE')
        ->and($body['departureTime'])->toBe('2026-06-26T10:00:00Z');

    Carbon::setTestNow();
});

it('omits the traffic-aware preference for non-driving departures', function () {
    Carbon::setTestNow('2026-06-26 10:00:00');

    $body = (new DistanceQuery(new Location(1, 2), new Location(3, 4), TravelMode::Walking, now()))->toRoutesBody();

    expect($body)->not->toHaveKey('routingPreference')
        ->and($body)->toHaveKey('departureTime');

    Carbon::setTestNow();
});

it('builds a routes body for multiple destinations', function () {
    $body = (new DistanceQuery(
        new Location(1, 2),
        new MultipleLocations([new Location(3, 4), new Location(5, 6)]),
    ))->toRoutesBody();

    expect($body['destinations'])->toHaveCount(2);
});

it('switches travel mode immutably', function () {
    $base = new DistanceQuery(new Location(1, 2), new Location(3, 4));

    expect($base->walking()->type)->toBe(TravelMode::Walking)
        ->and($base->bicycling()->type)->toBe(TravelMode::Bicycling)
        ->and($base->transit()->type)->toBe(TravelMode::Transit)
        ->and($base->travellingBy(TravelMode::Walking)->type)->toBe(TravelMode::Walking)
        ->and($base->driving()->type)->toBe(TravelMode::Driving)
        ->and($base->type)->toBe(TravelMode::Driving);
});

it('normalises a travel-mode string in the distance query', function () {
    expect((new DistanceQuery(new Location(1, 2), new Location(3, 4), 'walking'))->type)->toBe(TravelMode::Walking);
});

it('builds a text search body', function () {
    $body = (new TextSearchQuery('pizza'))
        ->inRegion('sk')
        ->take(5)
        ->ofType('restaurant')
        ->openNow()
        ->withMinRating(4.0)
        ->withPriceLevels('PRICE_LEVEL_MODERATE')
        ->rankBy('RELEVANCE')
        ->withPageToken('next')
        ->preferInArea((new LocationDefinition)->circle(new Location(1, 2)))
        ->toBody();

    expect($body)
        ->toHaveKey('textQuery', 'pizza')
        ->toHaveKey('regionCode', 'sk')
        ->toHaveKey('pageSize', 5)
        ->toHaveKey('includedType', 'restaurant')
        ->toHaveKey('openNow', true)
        ->toHaveKey('minRating', 4.0)
        ->toHaveKey('priceLevels', ['PRICE_LEVEL_MODERATE'])
        ->toHaveKey('rankPreference', 'RELEVANCE')
        ->toHaveKey('pageToken', 'next')
        ->toHaveKey('locationBias');
});

it('builds a text search body that can be restricted', function () {
    $body = (new TextSearchQuery)->withText('pizza')->inLanguage('sk')
        ->restrictTo((new LocationDefinition)->circle(new Location(1, 2)))->toBody();

    expect($body)->toHaveKey('locationRestriction')->toHaveKey('languageCode', 'sk');
});

it('builds a nearby search body', function () {
    $body = (new NearbySearchQuery(new Location(1, 2), radius: 1500))
        ->withinTypes('cafe')
        ->excludingTypes('bar')
        ->take(10)
        ->rankBy('DISTANCE')
        ->inLanguage('sk')
        ->withRadius(2000)
        ->toBody();

    expect($body['locationRestriction']['circle']['radius'])->toBe(2000)
        ->and($body)
        ->toHaveKey('includedTypes', ['cafe'])
        ->toHaveKey('excludedTypes', ['bar'])
        ->toHaveKey('maxResultCount', 10)
        ->toHaveKey('rankPreference', 'DISTANCE')
        ->toHaveKey('languageCode', 'sk');
});

it('rejects an out-of-range nearby search radius', function () {
    new NearbySearchQuery(new Location(1, 2), radius: 60000);
})->throws(PlacesException::class);
