<?php

namespace App\Repositories\Contracts;

interface HealthCheckRepositoryInterface
{
    public function isDatabaseReachable(): bool;

    public function isCacheReachable(): bool;
}
