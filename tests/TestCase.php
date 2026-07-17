<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Geolocation\GeolocationServiceProvider;
use RoundlyConsulting\GooglePlaces\GooglePlacesServiceProvider;
use RoundlyConsulting\HttpClientRateLimits\HttpClientRateLimitsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider google-places hard-requires, in registration order. A host
     * auto-discovers these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            HttpClientRateLimitsServiceProvider::class,
            GeolocationServiceProvider::class,
            GooglePlacesServiceProvider::class,
        ];
    }

    /**
     * No `migrationSources()`: google-places ships no migrations and opens no database
     * connection — it is an HTTP client. That is also why it carries no pgsql leg.
     *
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            'google-places.key' => 'GoogleApiKey',
            'google-places.http.retry_delay' => 0,
        ];
    }
}
