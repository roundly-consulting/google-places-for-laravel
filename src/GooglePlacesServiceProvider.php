<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use RoundlyConsulting\Geolocation\GeolocationManager;
use RoundlyConsulting\GooglePlaces\Commands\CheckCommand;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\Events\PlacesRequestFailed;
use RoundlyConsulting\GooglePlaces\Events\PlacesResponseReceived;
use RoundlyConsulting\GooglePlaces\Geolocation\GooglePlacesProvider;
use RoundlyConsulting\GooglePlaces\Listeners\LogPlacesActivity;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class GooglePlacesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('google-places')
            ->hasConfigFile()
            ->hasCommands([
                CheckCommand::class,
            ])
            ->contributesToAbout(static function (): array {
                $key = config('google-places.key');

                return [
                    'API key' => is_string($key) && $key !== '' ? 'SET' : 'MISSING',
                    'Cache' => config('google-places.cache.enabled') === true ? 'ENABLED' : 'OFF',
                    'Logging' => config('google-places.logging.enabled') === true ? 'ENABLED' : 'OFF',
                ];
            });
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(PlacesClient::class, static fn (Application $app): Places => new Places(
            $app->make(Dispatcher::class),
        ));

        $this->app->alias(PlacesClient::class, Places::class);
    }

    public function boot(): void
    {
        parent::boot();

        $events = $this->app->make(Dispatcher::class);
        $events->listen(PlacesResponseReceived::class, [LogPlacesActivity::class, 'handleResponseReceived']);
        $events->listen(PlacesRequestFailed::class, [LogPlacesActivity::class, 'handleRequestFailed']);

        // Register Google Places as a geolocation driver so a host running
        // geolocation-for-laravel can forward-/reverse-geocode through it by
        // adding `google_places` to its pipeline (or Geolocation::provider(...)).
        $this->app->make(GeolocationManager::class)->extend(
            'google_places',
            fn (): GooglePlacesProvider => new GooglePlacesProvider($this->app->make(PlacesClient::class)),
        );
    }
}
