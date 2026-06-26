<?php

declare(strict_types=1);

namespace RoundlyConsulting\GooglePlaces\DataTransferObjects;

final readonly class ApiCheckResult
{
    public function __construct(
        public string $api,
        public bool $ok,
        public string $detail,
    ) {}
}
