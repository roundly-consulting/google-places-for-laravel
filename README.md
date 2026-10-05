<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/google-places-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/google-places-for-laravel/main/art/hero.png" alt="Google Places for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/google-places-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/google-places-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/google-places-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/google-places-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/google-places-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/google-places-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Google Places for Laravel

Google's Places API (New), Routes API and Geocoding API from Laravel: autocomplete, place details,
text and nearby search, geocoding, distances and photos through one typed client with fluent query
objects, field masks, caching, rate limiting and a first-class fake for tests.

## Installation

Requires PHP 8.4, Laravel 12 or 13, and a Google Maps Platform API key with the Places API (New),
Routes API and Geocoding API enabled.

```bash
composer require roundly-consulting/google-places-for-laravel
```

Set `GOOGLE_PLACES_API_KEY` in `.env`; without it, calls throw `PlacesException` before any
request is sent.

## Usage

Suggest places while the user types, then look up the chosen one:

```php
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$predictions = GooglePlaces::autocomplete('Luxury Restaurant');   // Collection<AutocompletePrediction>

$place = GooglePlaces::details($predictions->first()->placeId);   // ?Place

$place->name;
$place->formattedAddress;
$place->coordinates();                                            // ?Location
$place->isOpenNow();

$photoUrl = GooglePlaces::photo($place->photos[0], maxWidth: 800)->url(); // key-free, safe for the browser
```

Search with filters and measure the trip:

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\{DistanceQuery, Location, TextSearchQuery};
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

$brunch = GooglePlaces::textSearch(
    (new TextSearchQuery('vegan brunch'))->openNow()->withMinRating(4.0)->take(10),
);                                                                // Collection<Place>

$trip = GooglePlaces::distance(new DistanceQuery(
    from: new Location(48.1486, 17.1077),                         // Bratislava
    to: new Location(48.2082, 16.3738),                           // Vienna
    type: TravelMode::Driving,
));

$trip->humanReadableDistance;                                     // "79.8 km"
$trip->humanReadableDuration;                                     // "1h 5m"
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/google-places-for-laravel](https://roundly-consulting.com/open-source/docs/google-places-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
