<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\GooglePlacesServiceProvider;
use RoundlyConsulting\GooglePlaces\Places;

it('registers the package commands', function (): void {
    expect(Artisan::all())->toHaveKey('google-places:check');
});

it('binds the places client behind its contract and class alias', function (): void {
    expect(app(PlacesClient::class))->toBeInstanceOf(Places::class)
        ->and(app(Places::class))->toBe(app(PlacesClient::class));
});

it('contributes a section to the about command', function (): void {
    $this->artisan('about', ['--only' => 'google-places'])
        ->expectsOutputToContain('SET')
        ->expectsOutputToContain('OFF')
        ->assertSuccessful();
});

it('reports a missing api key and enabled cache/logging to the about command', function (?string $key): void {
    config()->set('google-places.key', $key);
    config()->set('google-places.cache.enabled', true);
    config()->set('google-places.logging.enabled', true);

    $this->artisan('about', ['--only' => 'google-places'])
        ->expectsOutputToContain('MISSING')
        ->expectsOutputToContain('ENABLED')
        ->assertSuccessful();
})->with(['null' => [null], 'empty env' => [''], 'whitespace' => ['  ']]);

it('publishes the translations under the google-places-translations tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(GooglePlacesServiceProvider::class, 'google-places-translations');

    expect($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(realpath(__DIR__.'/../../resources/lang'))
        ->and(array_values($paths))->toBe([lang_path('vendor/google-places')]);
});
