<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

final class PlacesException extends Exception
{
    public static function fromResponse(Response $response): self
    {
        return new self(
            "Places API Error occured. Response: {$response->body()}",
            $response->status(),
        );
    }
}
