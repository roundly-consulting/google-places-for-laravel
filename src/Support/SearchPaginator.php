<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Support;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\Place;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\SearchPage;

/**
 * Transparently follows Google's `nextPageToken` for text/nearby search,
 * capped by `google-places.pagination.max_pages`.
 */
final class SearchPaginator
{
    /**
     * @param  Closure(?string): SearchPage  $fetcher  Fetches one page for the given token.
     */
    public function __construct(
        private readonly Closure $fetcher,
        private readonly int $maxPages,
        private readonly string $endpoint = 'search',
    ) {}

    /**
     * Lazily yield each page as a collection of places, following tokens on demand.
     *
     * @return LazyCollection<int, Collection<int, Place>>
     */
    public function pages(): LazyCollection
    {
        return LazyCollection::make(function (): iterable {
            $token = null;
            $page = 0;

            do {
                $result = ($this->fetcher)($token);
                $page++;

                yield collect($result->places);

                $token = $result->nextPageToken;

                if ($token !== null && $token !== '' && $page >= $this->maxPages) {
                    Log::warning('google-places: pagination stopped at the configured max-pages cap.', [
                        'endpoint' => $this->endpoint,
                        'max_pages' => $this->maxPages,
                    ]);

                    break;
                }
            } while ($token !== null && $token !== '');
        });
    }

    /**
     * Lazily yield every place across all pages.
     *
     * @return LazyCollection<int, Place>
     */
    public function cursor(): LazyCollection
    {
        return $this->pages()->flatMap(static fn (Collection $page): Collection => $page->values());
    }

    /**
     * Eagerly collect every place across all pages into one collection.
     *
     * @return Collection<int, Place>
     */
    public function all(): Collection
    {
        return $this->cursor()->collect()->values();
    }
}
