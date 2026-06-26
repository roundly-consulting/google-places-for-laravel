<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;

final class GooglePlacesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/google-places.php', 'google-places');

        $this->app->singleton(PlacesClient::class, static fn (Application $app): Places => new Places(
            $app->make(Dispatcher::class),
        ));

        $this->app->alias(PlacesClient::class, Places::class);
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
