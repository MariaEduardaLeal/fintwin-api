<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckRepository implements HealthCheckRepositoryInterface
{
    public function isDatabaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function isCacheReachable(): bool
    {
        try {
            $key = 'fintwin:healthcheck:ping';
            Cache::put($key, 'ok', 10);

            return Cache::get($key) === 'ok';
        } catch (Throwable) {
            return false;
        }
    }
}
