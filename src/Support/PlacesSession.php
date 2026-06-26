<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompletePrediction;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\AutocompleteQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\DetailsQuery;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\Exceptions\PlacesException;

/**
 * Ties an autocomplete burst plus the final details() call into one Google
 * billing session by reusing a single session token. The session is consumed
 * (invalidated) once details() resolves, per Google's session semantics.
 */
final class PlacesSession
{
    private readonly string $token;

    private bool $finished = false;

    public function __construct(
        private readonly PlacesClient $client,
        ?string $token = null,
    ) {
        $this->token = $token ?? (string) Str::uuid();
    }

    public function token(): string
    {
        return $this->token;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    /**
     * @return Collection<int, AutocompletePrediction>
     *
     * @throws PlacesException
     */
    public function autocomplete(AutocompleteQuery|string $query): Collection
    {
        $this->guard();

        $query = is_string($query) ? new AutocompleteQuery($query) : $query;

        return $this->client->autocomplete($query->usingSessionToken($this->token));
    }

    /**
     * Resolve the chosen place and close the session.
     *
     * @throws PlacesException
     */
    public function details(DetailsQuery|string $query): ?Place
    {
        $this->guard();

        $query = is_string($query) ? new DetailsQuery($query) : $query;

        $place = $this->client->details(new DetailsQuery(
            place: $query->place,
            fields: $query->fields,
            language: $query->language,
            sessionToken: $this->token,
        ));

        $this->finished = true;

        return $place;
    }

    /**
     * @throws PlacesException
     */
    private function guard(): void
    {
        if ($this->finished) {
            throw PlacesException::sessionFinished();
        }
    }
}
