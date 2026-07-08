<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Geolocation\GeolocationServiceProvider;
use RoundlyConsulting\GooglePlaces\GooglePlacesServiceProvider;
use RoundlyConsulting\HttpClientRateLimits\HttpClientRateLimitsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            HttpClientRateLimitsServiceProvider::class,
            GeolocationServiceProvider::class,
            GooglePlacesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('google-places.key', 'GoogleApiKey');
        $app['config']->set('google-places.http.retry_delay', 0);
    }
}
