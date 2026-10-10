<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DistanceQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\GeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Location;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\LocationDefinition;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\MatrixQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\NearbySearchQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ReverseGeocodingQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\TextSearchQuery;
use RoundlyConsulting\GooglePlaces\Enums\TravelMode;

/**
 * The cached queries, each in its plain shape.
 *
 * @return array<class-string, object>
 */
function cachedQueries(): array
{
    return [
        DetailsQuery::class => new DetailsQuery('place-1'),
        ReverseGeocodingQuery::class => new ReverseGeocodingQuery(new Location(1, 2)),
        GeocodingQuery::class => new GeocodingQuery('1 Main St'),
        TextSearchQuery::class => new TextSearchQuery('pizza'),
        NearbySearchQuery::class => new NearbySearchQuery(new Location(1, 2)),
        DistanceQuery::class => new DistanceQuery(new Location(1, 1), new Location(2, 2)),
        MatrixQuery::class => new MatrixQuery([new Location(1, 1)], [new Location(2, 2)]),
    ];
}

/**
 * Every wither of every cached query, applied to a query already made fresh.
 *
 * @return array<string, array{0: class-string, 1: Closure(object): object}>
 */
function freshWithers(): array
{
    $at = Carbon::parse('2026-10-10 10:00:00');
    $area = (new LocationDefinition)->circle(new Location(1, 2));

    return [
        'DetailsQuery::fresh' => [DetailsQuery::class, fn (DetailsQuery $q) => $q->fresh()],
        'ReverseGeocodingQuery::fresh' => [ReverseGeocodingQuery::class, fn (ReverseGeocodingQuery $q) => $q->fresh()],
        'GeocodingQuery::fresh' => [GeocodingQuery::class, fn (GeocodingQuery $q) => $q->fresh()],
        'GeocodingQuery::withAddress' => [GeocodingQuery::class, fn (GeocodingQuery $q) => $q->withAddress('2 Main St')],
        'GeocodingQuery::inLanguage' => [GeocodingQuery::class, fn (GeocodingQuery $q) => $q->inLanguage('sk')],
        'GeocodingQuery::inRegion' => [GeocodingQuery::class, fn (GeocodingQuery $q) => $q->inRegion('sk')],
        'GeocodingQuery::filterBy' => [GeocodingQuery::class, fn (GeocodingQuery $q) => $q->filterBy('country:SK')],
        'TextSearchQuery::fresh' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->fresh()],
        'TextSearchQuery::withText' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->withText('pasta')],
        'TextSearchQuery::inLanguage' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->inLanguage('sk')],
        'TextSearchQuery::inRegion' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->inRegion('sk')],
        'TextSearchQuery::take' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->take(1)],
        'TextSearchQuery::ofType' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->ofType('cafe')],
        'TextSearchQuery::openNow' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->openNow()],
        'TextSearchQuery::withMinRating' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->withMinRating(4.0)],
        'TextSearchQuery::withPriceLevels' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->withPriceLevels('PRICE_LEVEL_MODERATE')],
        'TextSearchQuery::preferInArea' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->preferInArea($area)],
        'TextSearchQuery::restrictTo' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->restrictTo($area)],
        'TextSearchQuery::rankBy' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->rankBy('DISTANCE')],
        'TextSearchQuery::nearby' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->nearby(1, 2)],
        'TextSearchQuery::withinBounds' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->withinBounds(new Location(1, 2), new Location(3, 4))],
        'TextSearchQuery::withPageToken' => [TextSearchQuery::class, fn (TextSearchQuery $q) => $q->withPageToken('next')],
        'NearbySearchQuery::fresh' => [NearbySearchQuery::class, fn (NearbySearchQuery $q) => $q->fresh()],
        'NearbySearchQuery::withRadius' => [NearbySearchQuery::class, fn (NearbySearchQuery $q) => $q->withRadius(100)],
        'NearbySearchQuery::withinTypes' => [NearbySearchQuery::class, fn (NearbySearchQuery $q) => $q->withinTypes('cafe')],
        'NearbySearchQuery::excludingTypes' => [NearbySearchQuery::class, fn (NearbySearchQuery $q) => $q->excludingTypes('bar')],
        'NearbySearchQuery::take' => [NearbySearchQuery::class, fn (NearbySearchQuery $q) => $q->take(1)],
        'NearbySearchQuery::rankBy' => [NearbySearchQuery::class, fn (NearbySearchQuery $q) => $q->rankBy('DISTANCE')],
        'NearbySearchQuery::inLanguage' => [NearbySearchQuery::class, fn (NearbySearchQuery $q) => $q->inLanguage('sk')],
        'DistanceQuery::fresh' => [DistanceQuery::class, fn (DistanceQuery $q) => $q->fresh()],
        'DistanceQuery::driving' => [DistanceQuery::class, fn (DistanceQuery $q) => $q->driving()],
        'DistanceQuery::walking' => [DistanceQuery::class, fn (DistanceQuery $q) => $q->walking()],
        'DistanceQuery::bicycling' => [DistanceQuery::class, fn (DistanceQuery $q) => $q->bicycling()],
        'DistanceQuery::transit' => [DistanceQuery::class, fn (DistanceQuery $q) => $q->transit()],
        'DistanceQuery::travellingBy' => [DistanceQuery::class, fn (DistanceQuery $q) => $q->travellingBy(TravelMode::Walking)],
        'DistanceQuery::departingAt' => [DistanceQuery::class, fn (DistanceQuery $q) => $q->departingAt($at)],
        'MatrixQuery::fresh' => [MatrixQuery::class, fn (MatrixQuery $q) => $q->fresh()],
        'MatrixQuery::driving' => [MatrixQuery::class, fn (MatrixQuery $q) => $q->driving()],
        'MatrixQuery::walking' => [MatrixQuery::class, fn (MatrixQuery $q) => $q->walking()],
        'MatrixQuery::bicycling' => [MatrixQuery::class, fn (MatrixQuery $q) => $q->bicycling()],
        'MatrixQuery::transit' => [MatrixQuery::class, fn (MatrixQuery $q) => $q->transit()],
        'MatrixQuery::travellingBy' => [MatrixQuery::class, fn (MatrixQuery $q) => $q->travellingBy(TravelMode::Walking)],
        'MatrixQuery::departingAt' => [MatrixQuery::class, fn (MatrixQuery $q) => $q->departingAt($at)],
    ];
}

it('is not fresh by default', function (object $query) {
    expect($query->fresh)->toBeFalse();
})->with(fn (): array => cachedQueries());

it('keeps the fresh flag through every wither', function (string $class, Closure $wither) {
    $fresh = $wither(cachedQueries()[$class]->fresh());

    expect($fresh)->toBeInstanceOf($class)
        ->and($fresh->fresh)->toBeTrue();
})->with(fn (): array => freshWithers());

it('lists every wither of every cached query', function () {
    $listed = array_keys(freshWithers());

    $withers = [];

    foreach (array_keys(cachedQueries()) as $class) {
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $method->isStatic() && (string) $method->getReturnType() === 'self') {
                $withers[] = $reflection->getShortName().'::'.$method->getName();
            }
        }
    }

    sort($listed);
    sort($withers);

    expect($withers)->toBe($listed);
});

it('can be switched back off', function (object $query) {
    expect($query->fresh()->fresh(false)->fresh)->toBeFalse();
})->with(fn (): array => cachedQueries());

it('never puts the flag on the wire', function (object $query) {
    $wire = static fn (object $q): array => match (true) {
        $q instanceof DetailsQuery, $q instanceof ReverseGeocodingQuery, $q instanceof GeocodingQuery => $q->toRequest(),
        $q instanceof TextSearchQuery, $q instanceof NearbySearchQuery => $q->toBody(),
        $q instanceof DistanceQuery, $q instanceof MatrixQuery => $q->toRoutesBody(),
    };

    expect($wire($query->fresh()))->toBe($wire($query))
        ->and(strtolower((string) json_encode($wire($query->fresh()))))->not->toContain('fresh');
})->with(fn (): array => cachedQueries());

it('takes the flag as a trailing named argument', function () {
    expect((new DetailsQuery('place-1', fresh: true))->fresh)->toBeTrue()
        ->and((new ReverseGeocodingQuery(new Location(1, 2), fresh: true))->fresh)->toBeTrue()
        ->and((new GeocodingQuery('1 Main St', fresh: true))->fresh)->toBeTrue()
        ->and((new TextSearchQuery('pizza', fresh: true))->fresh)->toBeTrue()
        ->and((new NearbySearchQuery(new Location(1, 2), fresh: true))->fresh)->toBeTrue()
        ->and((new DistanceQuery(new Location(1, 1), new Location(2, 2), fresh: true))->fresh)->toBeTrue()
        ->and((new MatrixQuery([new Location(1, 1)], [new Location(2, 2)], fresh: true))->fresh)->toBeTrue();
});
