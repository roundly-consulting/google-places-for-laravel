<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

/**
 * One page of a paginated search: its places plus the token for the next page.
 */
final readonly class SearchPage
{
    /**
     * @param  list<Place>  $places
     */
    public function __construct(
        public array $places,
        public ?string $nextPageToken = null,
    ) {}

    public function hasMore(): bool
    {
        return $this->nextPageToken !== null && $this->nextPageToken !== '';
    }
}
