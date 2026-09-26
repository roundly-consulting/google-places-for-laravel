<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ApiCheckResult;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

/**
 * Preflight doctor: pings each configured Google API with the configured key
 * and reports which are enabled/reachable. The key is redacted in all output.
 */
final class CheckCommand extends Command
{
    protected $signature = 'google-places:check';

    protected $description = 'Check that the configured Google APIs are enabled and reachable.';

    public function handle(): int
    {
        $key = config('google-places.key');

        if (! is_string($key) || $key === '') {
            $this->error('No API key configured. Set GOOGLE_PLACES_API_KEY in your environment.');

            return self::FAILURE;
        }

        $this->line('Using API key '.$this->redact($key));

        $results = [
            $this->probe('Places API (New)', fn (): Response => $this->placesProbe($key)),
            $this->probe('Routes API', fn (): Response => $this->routesProbe($key)),
            $this->probe('Geocoding API', fn (): Response => $this->geocodingProbe($key)),
        ];

        $this->table(['API', 'Status', 'Detail'], array_map(
            static fn (ApiCheckResult $result): array => [
                $result->api,
                $result->ok ? 'OK' : 'FAILED',
                $result->detail,
            ],
            $results,
        ));

        $failed = array_filter($results, static fn (ApiCheckResult $result): bool => ! $result->ok);

        if ($failed !== []) {
            $this->error('One or more Google APIs are not reachable or not enabled.');

            return self::FAILURE;
        }

        $this->info('All configured Google APIs are enabled and reachable.');

        return self::SUCCESS;
    }

    /**
     * @param  Closure(): Response  $request
     */
    private function probe(string $api, Closure $request): ApiCheckResult
    {
        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            return new ApiCheckResult($api, false, 'Unreachable: '.$exception->getMessage());
        }

        // The legacy Geocoding API answers a refused key with HTTP 200 and a body `status`
        // of REQUEST_DENIED (recorded live), so an HTTP success alone is not "authorized".
        $status = PlacesException::statusFrom($response);

        if ($response->successful() && in_array($status, [null, 'OK', 'ZERO_RESULTS'], true)) {
            return new ApiCheckResult($api, true, 'Reachable and authorized.');
        }

        $message = PlacesException::fromResponse($response)->googleErrorMessage();

        $detail = trim(($response->status().' '.($status ?? '')).' '.($message ?? ''));

        return new ApiCheckResult($api, false, $this->redactKey($detail !== '' ? $detail : 'Request rejected.'));
    }

    private function placesProbe(string $key): Response
    {
        return $this->client(config('google-places.hosts.places'))
            ->withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'places.id',
            ])
            ->post('/places:searchText', ['textQuery' => 'test']);
    }

    private function routesProbe(string $key): Response
    {
        return $this->client(config('google-places.hosts.routes'))
            ->withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'originIndex,destinationIndex,condition',
            ])
            ->post('/distanceMatrix/v2:computeRouteMatrix', [
                'origins' => [$this->waypoint()],
                'destinations' => [$this->waypoint()],
                'travelMode' => 'DRIVE',
            ]);
    }

    private function geocodingProbe(string $key): Response
    {
        return $this->client(config('google-places.hosts.geocoding'))
            ->get('/geocode/json', ['address' => 'Googleplex', 'key' => $key]);
    }

    private function client(mixed $baseUrl): PendingRequest
    {
        return Http::baseUrl(is_string($baseUrl) ? rtrim($baseUrl, '/') : '')
            ->timeout((int) config('google-places.http.timeout', 10))
            ->connectTimeout((int) config('google-places.http.connect_timeout', 5));
    }

    /**
     * @return array<string, mixed>
     */
    private function waypoint(): array
    {
        return ['waypoint' => ['location' => ['latLng' => ['latitude' => 0, 'longitude' => 0]]]];
    }

    private function redact(string $key): string
    {
        return str_repeat('*', max(0, strlen($key) - 4)).substr($key, -4);
    }

    private function redactKey(string $text): string
    {
        $key = config('google-places.key');

        if (! is_string($key) || $key === '') {
            return $text;
        }

        return str_replace($key, $this->redact($key), $text);
    }
}
