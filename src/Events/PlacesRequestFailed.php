<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Events;

use RoundlyConsulting\GooglePlaces\Support\Redactor;

final readonly class PlacesRequestFailed
{
    /** Google's error message, or the transport failure's — credentials redacted. */
    public string $message;

    public function __construct(
        public string $endpoint,
        public int $httpStatus,
        public ?string $googleStatus = null,
        string $message = '',
    ) {
        $this->message = Redactor::redact($message);
    }
}
