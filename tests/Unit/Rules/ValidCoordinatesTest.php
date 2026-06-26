<?php

declare(strict_types=1);

use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\Rules\ValidCoordinates;

/**
 * @return list<string> The validation failures (empty when the value passes).
 */
function coordinateErrors(mixed $value): array
{
    $errors = [];

    (new ValidCoordinates)->validate('point', $value, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    return $errors;
}

it('passes a valid coordinate in several shapes', function (mixed $value) {
    expect(coordinateErrors($value))->toBe([]);
})->with([
    'location object' => [new Location(48.1, 17.1)],
    'list array' => [[48.1, 17.1]],
    'assoc lat/lng' => [['lat' => 48.1, 'lng' => 17.1]],
    'assoc latitude/longitude' => [['latitude' => -89.9, 'longitude' => 179.9]],
    'string' => ['48.1,17.1'],
    'edges' => [[90, -180]],
]);

it('fails an out-of-range latitude', function () {
    expect(coordinateErrors([91, 17]))->toContain('The :attribute latitude must be between -90 and 90.');
});

it('fails an out-of-range longitude', function () {
    expect(coordinateErrors([45, 181]))->toContain('The :attribute longitude must be between -180 and 180.');
});

it('fails an unparseable value', function (mixed $value) {
    expect(coordinateErrors($value))->toContain('The :attribute must be a valid latitude/longitude pair.');
})->with([
    'null' => [null],
    'plain string' => ['nope'],
    'single number' => [48.1],
    'non-numeric pair' => [['a', 'b']],
]);
