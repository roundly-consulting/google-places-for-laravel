<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;
use RoundlyConsulting\GooglePlaces\Support\PendingPhoto;

beforeEach(fn () => Http::preventStrayRequests());

function fakePhotoEndpoint(): void
{
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'skipHttpRedirect=true')) {
            return Http::response(['photoUri' => 'https://lh3.googleusercontent.com/key-free', 'name' => 'x']);
        }

        return Http::response('RAW-IMAGE-BYTES', 200, ['Content-Type' => 'image/jpeg']);
    });
}

it('returns a pending photo from the facade', function () {
    expect(GooglePlaces::photo('places/p1/photos/abc'))->toBeInstanceOf(PendingPhoto::class);
});

it('resolves a key-free media url with header auth', function () {
    fakePhotoEndpoint();

    $url = GooglePlaces::photo('places/p1/photos/abc', 400, 300)->url();

    expect($url)->toBe('https://lh3.googleusercontent.com/key-free');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Goog-Api-Key', 'GoogleApiKey')
        && str_contains($request->url(), 'maxWidthPx=400')
        && str_contains($request->url(), 'skipHttpRedirect=true')
        && ! str_contains($request->url(), 'key=GoogleApiKey'));
});

it('fetches the raw photo bytes', function () {
    fakePhotoEndpoint();

    expect(GooglePlaces::photo('places/p1/photos/abc')->contents())->toBe('RAW-IMAGE-BYTES');
});

it('saves the photo to a storage disk', function () {
    fakePhotoEndpoint();

    Storage::fake('photos');

    $path = GooglePlaces::photo('places/p1/photos/abc')->save('photos', 'places/p1.jpg');

    expect($path)->toBe('places/p1.jpg');

    Storage::disk('photos')->assertExists('places/p1.jpg');
    expect(Storage::disk('photos')->get('places/p1.jpg'))->toBe('RAW-IMAGE-BYTES');
});

it('throws a missing-key exception when no key is configured', function () {
    config()->set('google-places.key', null);

    GooglePlaces::photo('places/p1/photos/abc')->contents();
})->throws(PlacesException::class, 'API key is missing');

it('throws when the media endpoint fails', function () {
    Http::fake([
        'places.googleapis.com/v1/*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404),
    ]);

    GooglePlaces::photo('places/p1/photos/missing')->contents();
})->throws(PlacesException::class);

it('throws when resolving the url fails', function () {
    Http::fake([
        'places.googleapis.com/v1/*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403),
    ]);

    GooglePlaces::photo('places/p1/photos/x')->url();
})->throws(PlacesException::class);

it('throws when the media endpoint is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    GooglePlaces::photo('places/p1/photos/x')->contents();
})->throws(PlacesException::class);
