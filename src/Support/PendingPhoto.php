<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Support;

use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

/**
 * One Google place photo. Every request goes through the {@see PlacesClient}
 * that built the handle — so it is throttled, reported and, under
 * `GooglePlaces::fake()`, recorded instead of sent. The API key travels as a
 * request header and is never exposed to the browser; url() returns Google's
 * key-free media URL.
 */
final readonly class PendingPhoto
{
    public function __construct(
        private PlacesClient $client,
        private string $name,
        private int $maxWidth = 1600,
        private int $maxHeight = 1600,
    ) {}

    /**
     * Resolve the final, key-free media URL (safe to hand to a browser).
     *
     * @throws PlacesException
     */
    public function url(): string
    {
        return $this->client->photoUri($this->name, $this->maxWidth, $this->maxHeight);
    }

    /**
     * The raw photo bytes.
     *
     * @throws PlacesException
     */
    public function contents(): string
    {
        return $this->client->photoContents($this->name, $this->maxWidth, $this->maxHeight);
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
}
