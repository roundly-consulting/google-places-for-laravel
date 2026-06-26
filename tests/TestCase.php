<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\GooglePlaces\GooglePlacesServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            GooglePlacesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('google-places.key', 'GoogleApiKey');
    }
}
