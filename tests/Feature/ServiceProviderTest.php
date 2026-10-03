<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
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
