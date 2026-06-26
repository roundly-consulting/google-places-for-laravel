<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\GooglePlaces\Commands\CheckCommand;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;
use RoundlyConsulting\GooglePlaces\Listeners\LogPlacesActivity;

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
        $events = $this->app->make(Dispatcher::class);
        $events->listen(PlacesResponseReceived::class, [LogPlacesActivity::class, 'handleResponseReceived']);
        $events->listen(PlacesRequestFailed::class, [LogPlacesActivity::class, 'handleRequestFailed']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/google-places.php' => config_path('google-places.php'),
            ], 'google-places-config');
        }
    }
}
