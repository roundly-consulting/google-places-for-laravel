<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Exceptions;

use RoundlyConsulting\PackageToolkit\Concerns\ProvidesRetryAfter;
use RoundlyConsulting\PackageToolkit\Contracts\HasRetryAfter;

/**
 * Thrown when the client-side rate limiter would defer a call past its
 * configured `max_wait` ceiling for an API surface — the opt-in fail-fast path.
 * Extends PlacesException so `catch (PlacesException)` keeps catching
 * everything, and carries the retry hint through the toolkit's HasRetryAfter
 * contract so hosts can turn it into a `Retry-After` header.
 */
final class RateLimitExceededException extends PlacesException implements HasRetryAfter
{
    use ProvidesRetryAfter;

    protected function __construct(
        string $message,
        public readonly string $surface,
    ) {
        parent::__construct($message, code: 429);
    }

    public static function for(string $surface, int $retryAfterSeconds): self
    {
        $exception = new self(
            "Google Places rate limit for the [{$surface}] surface exceeded. Retry in {$retryAfterSeconds} second(s).",
            $surface,
        );

        return $exception->withRetryAfter($retryAfterSeconds);
    }

    /**
     * Always — this IS the rate limit. It carries no Google status (the call never left),
     * so the inherited status check answered `false`, and a caller following the
     * documented `isRateLimited()` branch did not back off from the one exception that
     * asks it to.
     */
    public function isRateLimited(): bool
    {
        return true;
    }
}
