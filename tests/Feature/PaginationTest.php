<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;

beforeEach(fn () => Http::preventStrayRequests());

function pageResponse(string $name, ?string $token): array
{
    $place = placeResponse();
    $place['displayName']['text'] = $name;

    $body = ['places' => [$place]];

    if ($token !== null) {
        $body['nextPageToken'] = $token;
    }

    return $body;
}

it('eagerly collects every page following the next page token', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::sequence()
            ->push(pageResponse('Page 1', 'token-2'))
            ->push(pageResponse('Page 2', 'token-3'))
            ->push(pageResponse('Page 3', null)),
    ]);

    $all = places()->textSearchPaginated('museums')->all();

    expect($all)->toHaveCount(3)
        ->and($all->map(fn (Place $p): string => $p->name)->all())->toBe(['Page 1', 'Page 2', 'Page 3']);

    Http::assertSentCount(3);

    Http::assertSent(fn (Request $request): bool => str_contains($request->header('X-Goog-FieldMask')[0], 'nextPageToken'));
});

it('passes the next page token back to google on each page', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::sequence()
            ->push(pageResponse('Page 1', 'token-2'))
            ->push(pageResponse('Page 2', null)),
    ]);

    places()->textSearchPaginated('museums')->all();

    Http::assertSent(fn (Request $request): bool => ($request['pageToken'] ?? null) === 'token-2');
});

it('exposes a lazy cursor that pages on demand', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::sequence()
            ->push(pageResponse('Page 1', 'token-2'))
            ->push(pageResponse('Page 2', null)),
    ]);

    $cursor = places()->textSearchPaginated('museums')->cursor();

    expect($cursor)->toBeInstanceOf(LazyCollection::class)
        ->and($cursor->first())->toBeInstanceOf(Place::class)->name->toBe('Page 1');
});

it('stops at the configured max-pages cap and logs a warning', function () {
    config()->set('google-places.pagination.max_pages', 2);

    Log::spy();

    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::sequence()
            ->push(pageResponse('Page 1', 'token-2'))
            ->push(pageResponse('Page 2', 'token-3'))
            ->push(pageResponse('Page 3', 'token-4')),
    ]);

    $all = places()->textSearchPaginated('museums')->all();

    expect($all)->toHaveCount(2);

    Http::assertSentCount(2);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'max-pages'))->once();
});

it('returns a single page for nearby search (new api does not paginate)', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchNearby' => Http::response(pageResponse('Near', null)),
    ]);

    $all = places()->nearbySearchPaginated(new NearbySearchQuery(new Location(1, 2), radius: 1000))->all();

    expect($all)->toHaveCount(1);

    Http::assertSentCount(1);
});

it('asks text search for its next page token but never nearby search, whose response has none', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(pageResponse('Text', null)),
        // A token here would be a protocol violation; it must not make the paginator ask again.
        'places.googleapis.com/v1/places:searchNearby' => Http::response(pageResponse('Near', 'not-a-nearby-field')),
    ]);

    places()->textSearchPaginated('museums')->all();
    $nearby = places()->nearbySearchPaginated(new NearbySearchQuery(new Location(1, 2), radius: 1000))->all();

    expect($nearby)->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'places:searchText')
        && str_contains($request->header('X-Goog-FieldMask')[0], ',nextPageToken'));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'places:searchNearby')
        && $request->header('X-Goog-FieldMask')[0] === config('google-places.field_masks.search'));
    Http::assertSentCount(2);
});
