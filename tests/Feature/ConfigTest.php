<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\GooglePlaces\GooglePlacesServiceProvider;

it('merges the package config with sensible defaults', function () {
    expect(config('google-places.key'))->toBe('GoogleApiKey')
        ->and(config('google-places.base_url'))->toBe('https://maps.googleapis.com/maps/api');
});

it('publishes the config file under its tag', function () {
    $paths = ServiceProvider::pathsToPublish(GooglePlacesServiceProvider::class, 'google-places-config');

    expect($paths)->not->toBeEmpty()
        ->and(array_keys($paths)[0])->toEndWith('config/google-places.php')
        ->and(array_values($paths)[0])->toEndWith('google-places.php');
});
