<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class SimulationCacheInvalidator
{
    private readonly CacheRepository $cache;

    public function __construct(?CacheRepository $cache = null)
    {
        $this->cache = $cache ?? Cache::store();
    }

    public function versionKey(int $userId): string
    {
        return "fintwin:simulation:version:{$userId}";
    }

    public function currentVersion(int $userId): int
    {
        return (int) $this->cache->get($this->versionKey($userId), 1);
    }

    public function invalidateForUser(int $userId): int
    {
        $key = $this->versionKey($userId);

        if (! $this->cache->has($key)) {
            $this->cache->forever($key, 2);

            return 2;
        }

        $next = $this->cache->increment($key);

        if ($next === false) {
            $next = $this->currentVersion($userId) + 1;
            $this->cache->forever($key, $next);
        }

        return (int) $next;
    }
}
