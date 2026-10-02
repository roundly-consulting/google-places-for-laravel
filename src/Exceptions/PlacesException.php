<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Exceptions;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RoundlyConsulting\GooglePlaces\Support\Redactor;

class PlacesException extends Exception
{
    /**
     * `google.rpc.ErrorInfo` reasons that are about the KEY or the PROJECT, never the
     * request — whichever status Google files them under. An invalid or expired key comes
     * back as `INVALID_ARGUMENT`, the same status as a malformed request, so the status
     * alone would send a caller off to "fix the request" when the key is what is wrong.
     */
    private const array DENIED_REASONS = [
        'API_KEY_INVALID',
        'API_KEY_EXPIRED',
        'API_KEY_SERVICE_BLOCKED',
        'API_KEY_HTTP_REFERRER_BLOCKED',
        'API_KEY_IP_ADDRESS_BLOCKED',
        'API_KEY_ANDROID_APP_BLOCKED',
        'API_KEY_IOS_APP_BLOCKED',
        'SERVICE_DISABLED',
        'BILLING_DISABLED',
        'CONSUMER_INVALID',
    ];

    /**
     * Statuses that mean "check the key, billing, or the enabled APIs". `OVER_DAILY_LIMIT`
     * is the legacy Geocoding API's answer for a missing/invalid key, disabled billing, or a
     * self-imposed cap — none of which a retry fixes.
     */
    private const array DENIED_STATUSES = ['PERMISSION_DENIED', 'UNAUTHENTICATED', 'REQUEST_DENIED', 'OVER_DAILY_LIMIT'];

    public readonly ?string $googleErrorMessage;

    /**
     * Every message is redacted here, at the one place they all pass through: a transport
     * failure's message ends in the request URL (the Geocoding key rides in its query
     * string), and Google may echo a key back in an error body.
     */
    protected function __construct(
        string $message,
        int $code = 0,
        public readonly ?string $googleStatus = null,
        ?string $googleErrorMessage = null,
        public readonly ?Response $response = null,
        public readonly ?string $googleReason = null,
    ) {
        $this->googleErrorMessage = $googleErrorMessage === null ? null : Redactor::redact($googleErrorMessage);

        parent::__construct(Redactor::redact($message), $code);
    }

    public static function fromResponse(Response $response): self
    {
        $errorMessage = self::error($response)['message'] ?? $response->json('error_message');

        return new self(
            "Places API request failed. Response: {$response->body()}",
            $response->status(),
            self::statusFrom($response),
            is_string($errorMessage) ? $errorMessage : null,
            $response,
            self::reasonFrom($response),
        );
    }

    public static function missingApiKey(): self
    {
        return new self('Google Places API key is missing. Set GOOGLE_PLACES_API_KEY in your environment.');
    }

    /**
     * The transport exception is deliberately NOT chained as `previous`: its message carries
     * the full request URL, and an error tracker would report it unredacted.
     */
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
     * Read the Google status string from the Places (New) error body (`error.status`), the
     * Routes API's array-wrapped one (`[0].error.status`), or the Geocoding API body
     * (top-level `status`).
     */
    public static function statusFrom(Response $response): ?string
    {
        $status = self::error($response)['status'] ?? $response->json('status');

        return is_string($status) ? $status : null;
    }

    /**
     * The `google.rpc.ErrorInfo` reason (`API_KEY_INVALID`, `SERVICE_DISABLED`, …) of a
     * Places (New) / Routes error, or null — the legacy Geocoding API has none.
     */
    public static function reasonFrom(Response $response): ?string
    {
        $details = self::error($response)['details'] ?? [];

        foreach (is_array($details) ? $details : [] as $detail) {
            if (is_array($detail)
                && str_ends_with((string) ($detail['@type'] ?? ''), 'google.rpc.ErrorInfo')
                && is_string($detail['reason'] ?? null)) {
                return $detail['reason'];
            }
        }

        return null;
    }

    /**
     * The `google.rpc.Status` error object. Places API (New) answers `{"error": {…}}`; the
     * Routes API's computeRouteMatrix streams, so even its error arrives as a one-element
     * ARRAY, `[{"error": {…}}]` (recorded live) — read as `error.status`, that shape
     * yields nothing, and every Routes failure used to lose its classification.
     *
     * @return array<mixed>
     */
    private static function error(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            return [];
        }

        $error = array_is_list($body) && is_array($body[0] ?? null)
            ? ($body[0]['error'] ?? null)
            : ($body['error'] ?? null);

        return is_array($error) ? $error : [];
    }

    public function googleStatus(): ?string
    {
        return $this->googleStatus;
    }

    public function googleReason(): ?string
    {
        return $this->googleReason;
    }

    public function googleErrorMessage(): ?string
    {
        return $this->googleErrorMessage;
    }

    public function isRateLimited(): bool
    {
        return in_array($this->googleStatus, ['RESOURCE_EXHAUSTED', 'OVER_QUERY_LIMIT'], true);
    }

    /**
     * The key, billing, or the enabled APIs need attention: an invalid/expired/restricted
     * key, an API not enabled for the key or the project, disabled billing.
     */
    public function isDenied(): bool
    {
        return in_array($this->googleStatus, self::DENIED_STATUSES, true)
            || in_array($this->googleReason, self::DENIED_REASONS, true);
    }

    /** The request itself is malformed — never a key problem filed under the same status. */
    public function isInvalidRequest(): bool
    {
        return in_array($this->googleStatus, ['INVALID_ARGUMENT', 'INVALID_REQUEST'], true)
            && ! $this->isDenied();
    }
}
