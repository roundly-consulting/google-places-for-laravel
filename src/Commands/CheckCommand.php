<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\GooglePlaces\Contracts\PlacesClient;
use RoundlyConsulting\GooglePlaces\DataTransferObjects\ApiCheckResult;
use RoundlyConsulting\GooglePlaces\Support\ConfigValue;
use RoundlyConsulting\GooglePlaces\Support\Redactor;

/**
 * Preflight doctor: a thin console face over `GooglePlaces::check()`. The key
 * is redacted in all output.
 */
final class CheckCommand extends Command
{
    protected $signature = 'google-places:check';

    protected $description = 'Check that the configured Google APIs are enabled and reachable.';

    public function handle(PlacesClient $places): int
    {
        $key = config('google-places.key');

        if (! is_string($key) || ! ConfigValue::isSet($key)) {
            $this->error('No API key configured. Set GOOGLE_PLACES_API_KEY in your environment.');

            return self::FAILURE;
        }

        $this->line('Using API key '.Redactor::mask($key));

        $results = $places->check();

        $this->table(['API', 'Status', 'Detail'], array_map(
            static fn (ApiCheckResult $result): array => [
                $result->api,
                $result->ok ? 'OK' : 'FAILED',
                $result->detail,
            ],
            $results,
        ));

        if (array_filter($results, static fn (ApiCheckResult $result): bool => ! $result->ok) !== []) {
            $this->error('One or more Google APIs are not reachable or not enabled.');

            return self::FAILURE;
        }

        $this->info('All configured Google APIs are enabled and reachable.');

        return self::SUCCESS;
    }
}
