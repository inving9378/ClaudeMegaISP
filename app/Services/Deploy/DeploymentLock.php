<?php

namespace App\Services\Deploy;

use Illuminate\Support\Facades\Cache;

class DeploymentLock
{
    const CACHE_KEY = 'deployment:running';
    const TTL_SECONDS = 600;

    public static function acquire(int $deploymentId): bool
    {
        return Cache::add(self::CACHE_KEY, $deploymentId, self::TTL_SECONDS);
    }

    public static function release(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isLocked(): bool
    {
        return Cache::has(self::CACHE_KEY);
    }

    public static function currentId(): ?int
    {
        return Cache::get(self::CACHE_KEY);
    }
}
