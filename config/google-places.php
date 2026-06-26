<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google API Key
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate every request to the Google Maps /
    | Places web services. Create one in the Google Cloud console and enable
    | the Places, Geocoding, and Distance Matrix APIs for it.
    |
    */

    'key' => env('GOOGLE_PLACES_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL every request is sent to. Override it only if you proxy the
    | Google Maps API through your own gateway; the default targets Google
    | directly.
    |
    */

    'base_url' => env('GOOGLE_PLACES_API_URL', 'https://maps.googleapis.com/maps/api'),

];
