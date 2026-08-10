<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;

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

/*
|--------------------------------------------------------------------------
| A store that refuses to unserialize classes
|--------------------------------------------------------------------------
|
| Laravel's `cache.serializable_classes` defaults to `false` (a guard against
| gadget chains if `APP_KEY` leaks), so a host's store hands back a
| `__PHP_Incomplete_Class` for anything object-shaped that was put into it.
| These run the package against exactly that, because the default `array` store
| does not serialize at all and therefore cannot see the difference.
*/

function refusesClasses(): void
{
    config()->set('cache.serializable_classes', false);
    config()->set('cache.stores.array.serialize', true);
    config()->set('google-places.cache.enabled', true);

    app('cache')->forgetDriver('array');
}

it('returns a real object from a WARM cache, not an incomplete one', function () {
    // The regression: the decoded `Place` used to be what was cached, so the request
    // that warmed the key got an object and every one after it got a
    // `__PHP_Incomplete_Class` — a `TypeError` against the `?Place` return type, for
    // the whole 24h TTL, on an endpoint that bills per miss.
    refusesClasses();

    $first = places()->details(new DetailsQuery('place-1'));
    $second = places()->details(new DetailsQuery('place-1'));

    expect($first)->toBeInstanceOf(Place::class)
        ->and($second)->toBeInstanceOf(Place::class)
        ->and($second->id)->toBe($first->id);

    Http::assertSentCount(1);
});

it('treats an entry it cannot read as a miss rather than throwing', function () {
    refusesClasses();

    // What a previous version of this package left behind: the object itself.
    Cache::put('google-places:details:'.sha1('{}'), new stdClass, 60);

    expect(places()->details(new DetailsQuery('place-1')))->toBeInstanceOf(Place::class);
});
