<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces;

use Illuminate\Support\ServiceProvider;

final class GooglePlacesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/google-places.php', 'google-places');

        $this->app->singleton(Places::class, static fn (): Places => new Places);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/google-places.php' => config_path('google-places.php'),
            ], 'google-places-config');
        }
    }
}
