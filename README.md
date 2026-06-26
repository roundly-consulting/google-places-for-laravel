<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/google-places-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=google-places-for-laravel">
    <img src="art/hero.png" alt="Google Places for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Google Places for Laravel

Query the Google Places API — place details, autocomplete, reverse geocoding, and distance
matrix — from Laravel through a small, fully typed client with expressive query objects and
data transfer objects.

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`
- A Google Maps Platform API key with the **Places**, **Geocoding**, and **Distance Matrix**
  APIs enabled

## Installation

```bash
composer require roundly-consulting/google-places-for-laravel
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="google-places-config"
```

## Configuration

The package reads its settings from `config/google-places.php`:

```php
return [
    // The API key used for every request. Required.
    'key' => env('GOOGLE_PLACES_API_KEY'),

    // The base URL requests are sent to. Override only when proxying Google.
    'base_url' => env('GOOGLE_PLACES_API_URL', 'https://maps.googleapis.com/maps/api'),
];
```

| Key        | Type     | Default                                   | Env                       | Purpose                                            |
|------------|----------|-------------------------------------------|---------------------------|----------------------------------------------------|
| `key`      | `string` | `null`                                    | `GOOGLE_PLACES_API_KEY`   | API key sent with every request (required).        |
| `base_url` | `string` | `https://maps.googleapis.com/maps/api`    | `GOOGLE_PLACES_API_URL`   | Base URL; change it only if you proxy the Google API. |

Set the key in your `.env`:

```dotenv
GOOGLE_PLACES_API_KEY=your-google-maps-api-key
```

The package works with zero extra configuration once the key is set.

## Usage

Resolve the client from the container or use the `GooglePlaces` facade. Both point at the
same singleton.

```php
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;
use RoundlyConsulting\GooglePlaces\Places;

$places = app(Places::class); // or use the GooglePlaces facade
```

### Autocomplete (place search)

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$predictions = GooglePlaces::autocomplete(new AutocompleteQuery(
    query: 'Luxury Restaurant',
));

/** @var AutocompletePrediction $prediction */
$prediction = $predictions->first();

echo $prediction->description;
echo $prediction->placeId;
echo $prediction->reference;
echo implode(', ', $prediction->types);
```

`AutocompleteQuery` exposes a fluent builder for the optional parameters:

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;

$query = (new AutocompleteQuery())
    ->query('Coffee')
    ->inLanguage('en')
    ->ofType('cafe', 'restaurant')
    ->preferInArea((new LocationDefinition())->circular(new Location(48.1486, 17.1077), radius: 2000))
    ->fromOrigin(new Location(48.1486, 17.1077))
    ->inRegion('sk')
    ->usingSessionToken('a-session-token');

$predictions = GooglePlaces::autocomplete($query);
```

### Place details

`details()` returns a `Place`, or `null` when Google reports `ZERO_RESULTS` / `NOT_FOUND`.

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$place = GooglePlaces::details(new DetailsQuery(
    place: $prediction->placeId,
));

if ($place instanceof Place) {
    echo $place->name;
    echo implode(', ', $place->types);

    echo $place->geometry?->location->latitude;
    echo $place->geometry?->location->longitude;

    foreach ($place->openingHours?->periods ?? [] as $period) {
        echo "{$period->day}: {$period->from}–{$period->to}";
    }

    // The raw Google payload is always available.
    $raw = $place->raw;
}
```

### Reverse geocoding

`geocode()` returns a collection of `ReverseGeocodingResult` for a latitude/longitude pair.

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$results = GooglePlaces::geocode(new ReverseGeocodingQuery(
    location: new Location(48.1486, 17.1077),
));

$result = $results->first();

echo $result->address;
echo $result->placeId;

foreach ($result->components as $component) {
    echo "{$component->longName} ({$component->shortName})";
}
```

### Distance matrix

`distance()` returns a `Distance` for a single destination, or a `Roundtrip` (which holds the
individual legs plus totals) when you pass `MultipleLocations`.

```php
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MultipleLocations;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Roundtrip;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

$distance = GooglePlaces::distance(new DistanceQuery(
    from: new Location(48.1486, 17.1077),
    to: new MultipleLocations([
        new Location(48.2082, 16.3738),
        new Location(50.0755, 14.4378),
    ]),
    type: 'driving',
    departureAt: now()->addHour(), // optional, enables traffic-aware durations
));

echo $distance->humanReadableDistance; // e.g. "330.4km"
echo $distance->distanceInMeters;      // e.g. 330400
echo $distance->humanReadableDuration; // e.g. "3h 20m"
echo $distance->durationInSeconds;     // e.g. 12000
echo $distance->type;                  // "driving"

if ($distance instanceof Roundtrip) {
    foreach ($distance->distances as $leg) {
        echo $leg->humanReadableDistance;
    }
}
```

### Photo URLs

```php
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

// $photoReference comes from a place details response.
$url = GooglePlaces::photoUrl($photoReference, maxWidth: 800, maxHeight: 600);
```

### Error handling

Any non-success response from Google throws a
`RoundlyConsulting\GooglePlaces\Exceptions\PlacesException`. Catch it to handle API errors:

```php
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;
use RoundlyConsulting\GooglePlaces\Facades\GooglePlaces;

try {
    $predictions = GooglePlaces::autocomplete($query);
} catch (PlacesException $e) {
    report($e);
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you discover a security vulnerability, please review
[our security policy](../../security/policy) on how to report it.

## Credits

- [Andrej Mihaliak](https://github.com/mihaliak)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
