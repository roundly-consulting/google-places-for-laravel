<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\GooglePlaces\GooglePlacesServiceProvider;

it('merges the package config with sensible defaults', function () {
    expect(config('google-places.key'))->toBe('GoogleApiKey')
        ->and(config('google-places.hosts.places'))->toBe('https://places.googleapis.com/v1')
        ->and(config('google-places.hosts.routes'))->toBe('https://routes.googleapis.com')
        ->and(config('google-places.hosts.geocoding'))->toBe('https://maps.googleapis.com/maps/api')
        ->and(config('google-places.field_masks.details'))->toContain('types')
        ->and(config('google-places.field_masks.details'))->toContain('photos')
        ->and(config('google-places.http.timeout'))->toBe(10)
        ->and(config('google-places.http.connect_timeout'))->toBe(5)
        ->and(config('google-places.cache.enabled'))->toBeFalse();
});

it('publishes the config file under its tag', function () {
    $paths = ServiceProvider::pathsToPublish(GooglePlacesServiceProvider::class, 'google-places-config');

    expect($paths)->not->toBeEmpty()
        ->and(array_keys($paths)[0])->toEndWith('config/google-places.php')
        ->and(array_values($paths)[0])->toEndWith('google-places.php');
});
