<?php

namespace App\Support\Ops;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SchedulerHeartbeat
{
    public const CACHE_KEY = 'ops:scheduler:last_run_at';

    public function touch(): void
    {
        Cache::put(self::CACHE_KEY, now()->toIso8601String(), now()->addDay());
    }

    public function lastRunAt(): ?Carbon
    {
        $value = Cache::get(self::CACHE_KEY);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isFresh(int $maxAgeMinutes = 10): bool
    {
        $lastRun = $this->lastRunAt();

        if ($lastRun === null) {
            return false;
        }

        return $lastRun->greaterThanOrEqualTo(now()->subMinutes($maxAgeMinutes));
    }
}
