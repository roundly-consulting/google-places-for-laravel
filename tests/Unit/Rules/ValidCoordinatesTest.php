<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
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

it('answers in slovak under the sk locale', function () {
    app()->setLocale('sk');

    expect(coordinateErrors('nope'))->toBe(['Pole :attribute musí obsahovať platnú dvojicu zemepisnej šírky a dĺžky.'])
        ->and(coordinateErrors([91, 181]))->toBe([
            'Zemepisná šírka v poli :attribute musí byť medzi -90 a 90.',
            'Zemepisná dĺžka v poli :attribute musí byť medzi -180 a 180.',
        ]);
});

it('names the attribute by its display or custom name through the validator', function () {
    $validator = Validator::make(
        ['home_location' => [91, 17], 'pin' => 'nope'],
        ['home_location' => [new ValidCoordinates], 'pin' => [new ValidCoordinates]],
        [],
        ['pin' => 'map pin'],
    );

    expect($validator->errors()->first('home_location'))->toBe('The home location latitude must be between -90 and 90.')
        ->and($validator->errors()->first('pin'))->toBe('The map pin must be a valid latitude/longitude pair.');
});

it('answers in slovak through the validator', function () {
    app()->setLocale('sk');

    $validator = Validator::make(['from' => [45, 181]], ['from' => [new ValidCoordinates]]);

    expect($validator->errors()->first('from'))->toBe('Zemepisná dĺžka v poli from musí byť medzi -180 a 180.');
});

it('lets the host override a message', function () {
    app('translator')->addLines(['validation.latitude' => 'X :attribute'], 'en', 'google-places');

    $validator = Validator::make(['point' => [91, 17]], ['point' => [new ValidCoordinates]]);

    expect($validator->errors()->first('point'))->toBe('X point');
});
