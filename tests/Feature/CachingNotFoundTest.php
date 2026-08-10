<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;

/**
 * "No such place" is an ANSWER, and a cacheable one.
 *
 * Its own file because the fake has to be the only one registered: a catch-all
 * registered first wins, and this needs every request to 404.
 */
beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['places.googleapis.com/v1/places/*' => Http::response([], 404)]);

    config()->set('cache.serializable_classes', false);
    config()->set('cache.stores.array.serialize', true);
    config()->set('google-places.cache.enabled', true);

    app('cache')->forgetDriver('array');
});

it('remembers a 404 instead of re-billing for a question already answered', function () {
    expect(places()->details(new DetailsQuery('missing')))->toBeNull()
        ->and(places()->details(new DetailsQuery('missing')))->toBeNull();

    // `Cache::remember` cannot tell a cached `null` from a miss, so this used to send
    // a paid request every single time somebody asked about a place that is not there.
    Http::assertSentCount(1);
});
