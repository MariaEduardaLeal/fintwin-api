<?php

namespace App\DTOs;

readonly class HealthCheckDto
{
    /**
     * @param  array<string, string>  $services
     */
    public function __construct(
        public string $status,
        public string $service,
        public string $environment,
        public array $services,
        public string $timestamp,
    ) {}
}
