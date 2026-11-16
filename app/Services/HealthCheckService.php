<?php

namespace App\Services;

use App\DTOs\HealthCheckDto;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;

class HealthCheckService
{
    public function __construct(
        private readonly HealthCheckRepositoryInterface $healthCheckRepository,
    ) {}

    public function check(): HealthCheckDto
    {
        $dbUp = $this->healthCheckRepository->isDatabaseReachable();
        $cacheUp = $this->healthCheckRepository->isCacheReachable();

        $allUp = $dbUp && $cacheUp;

        return new HealthCheckDto(
            status: $allUp ? 'ok' : 'degraded',
            service: (string) config('app.name', 'FinTwin'),
            environment: (string) config('app.env', 'production'),
            services: [
                'database' => $dbUp ? 'up' : 'down',
                'cache' => $cacheUp ? 'up' : 'down',
            ],
            timestamp: now()->toIso8601String(),
        );
    }
}
