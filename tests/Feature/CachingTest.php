<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;

beforeEach(function () {
    Http::preventStrayRequests();

    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response(placeResponse()),
    ]);
});

it('does not cache by default', function () {
    places()->details(new DetailsQuery('place-1'));
    places()->details(new DetailsQuery('place-1'));

    Http::assertSentCount(2);
});

it('caches identical lookups when enabled', function () {
    config()->set('google-places.cache.enabled', true);

    places()->details(new DetailsQuery('place-1'));
    places()->details(new DetailsQuery('place-1'));

    Http::assertSentCount(1);
});

it('uses a distinct cache key per query', function () {
    config()->set('google-places.cache.enabled', true);

    places()->details(new DetailsQuery('place-1'));
    places()->details(new DetailsQuery('place-2'));

    Http::assertSentCount(2);
});

it('never includes the api key in the cache key', function () {
    config()->set('google-places.cache.enabled', true);

    places()->details(new DetailsQuery('place-1'));

    config()->set('google-places.key', 'ADifferentKey');

    places()->details(new DetailsQuery('place-1'));

    Http::assertSentCount(1);
});
