<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

/**
 * Fetches the actual bytes behind a Google place photo. The API key is sent as
 * a request header and never exposed to the browser; url() returns Google's
 * key-free media URL.
 */
final class PendingPhoto
{
    public function __construct(
        private readonly string $name,
        private readonly int $maxWidth = 1600,
        private readonly int $maxHeight = 1600,
    ) {}

    /**
     * Resolve the final, key-free media URL (safe to hand to a browser).
     *
     * @throws PlacesException
     */
    public function url(): string
    {
        $response = $this->request(['skipHttpRedirect' => 'true']);

        if (! $response->successful()) {
            throw PlacesException::fromResponse($response);
        }

        $uri = $response->json('photoUri');

        return is_string($uri) ? $uri : '';
    }

    /**
     * The raw photo bytes.
     *
     * @throws PlacesException
     */
    public function contents(): string
    {
        $response = $this->request();

        if (! $response->successful()) {
            throw PlacesException::fromResponse($response);
        }

        return $response->body();
    }

    /**
     * Store the photo on a filesystem disk, returning the path on success.
     *
     * @throws PlacesException
     */
    public function save(string $disk, string $path): string
    {
        Storage::disk($disk)->put($path, $this->contents());

        return $path;
    }

    /**
     * @param  array<string, string>  $extra
     *
     * @throws PlacesException
     */
    private function request(array $extra = []): Response
    {
        try {
            return $this->client()->get($this->mediaUrl(), array_merge([
                'maxWidthPx' => (string) $this->maxWidth,
                'maxHeightPx' => (string) $this->maxHeight,
            ], $extra));
        } catch (ConnectionException $exception) {
            throw PlacesException::connectionFailed($exception);
        }
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['X-Goog-Api-Key' => $this->key()])
            ->timeout((int) config('google-places.http.timeout', 10))
            ->connectTimeout((int) config('google-places.http.connect_timeout', 5));
    }

    private function mediaUrl(): string
    {
        $host = config('google-places.hosts.places');
        $host = is_string($host) ? rtrim($host, '/') : '';

        return $host.'/'.ltrim($this->name, '/').'/media';
    }

    private function key(): string
    {
        $key = config('google-places.key');

        if (! is_string($key) || $key === '') {
            throw PlacesException::missingApiKey();
        }

        return $key;
    }
}
