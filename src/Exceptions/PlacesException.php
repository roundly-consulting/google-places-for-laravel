<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Exceptions;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

final class PlacesException extends Exception
{
    private function __construct(
        string $message,
        int $code = 0,
        public readonly ?string $googleStatus = null,
        public readonly ?string $googleErrorMessage = null,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message, $code);
    }

    public static function fromResponse(Response $response): self
    {
        $status = self::statusFrom($response);
        $errorMessage = $response->json('error.message') ?? $response->json('error_message');

        return new self(
            "Places API request failed. Response: {$response->body()}",
            $response->status(),
            $status,
            is_string($errorMessage) ? $errorMessage : null,
            $response,
        );
    }

    public static function missingApiKey(): self
    {
        return new self('Google Places API key is missing. Set GOOGLE_PLACES_API_KEY in your environment.');
    }

    public static function connectionFailed(ConnectionException $exception): self
    {
        return new self("Could not reach the Google Places API: {$exception->getMessage()}");
    }

    public static function tooManyPrimaryTypes(int $max = 5): self
    {
        return new self("A maximum of {$max} primary types may be requested.");
    }

    public static function tooManyRegionCodes(int $max = 15): self
    {
        return new self("A maximum of {$max} region codes may be requested.");
    }

    public static function invalidRadius(int $min, int $max): self
    {
        return new self("The search radius must be between {$min} and {$max} meters.");
    }

    public static function sessionFinished(): self
    {
        return new self('This autocomplete session has already been closed by a details() call. Start a new session with GooglePlaces::session().');
    }

    public static function routeNotFound(int $origin, int $destination, ?string $condition): self
    {
        return new self(
            "No route exists between origin {$origin} and destination {$destination} (condition: ".($condition ?? 'UNKNOWN').').'
        );
    }

    /**
     * Read the Google status string from either the Places/Routes error body
     * (`error.status`) or the Geocoding API body (top-level `status`).
     */
    public static function statusFrom(Response $response): ?string
    {
        $status = $response->json('error.status') ?? $response->json('status');

        return is_string($status) ? $status : null;
    }

    public function googleStatus(): ?string
    {
        return $this->googleStatus;
    }

    public function googleErrorMessage(): ?string
    {
        return $this->googleErrorMessage;
    }

    public function isRateLimited(): bool
    {
        return in_array($this->googleStatus, ['RESOURCE_EXHAUSTED', 'OVER_QUERY_LIMIT'], true);
    }

    public function isDenied(): bool
    {
        return in_array($this->googleStatus, ['PERMISSION_DENIED', 'REQUEST_DENIED'], true);
    }

    public function isInvalidRequest(): bool
    {
        return in_array($this->googleStatus, ['INVALID_ARGUMENT', 'INVALID_REQUEST'], true);
    }
}
