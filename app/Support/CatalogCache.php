<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Version-bumped cache keys for catalog API JSON (not image binaries).
 */
final class CatalogCache
{
    public const TTL_SECONDS = 900;

    private const VERSION_KEY = 'catalog:version';

    public static function remember(string $key, Closure $callback, ?int $ttl = null): mixed
    {
        return Cache::remember(
            self::key($key),
            $ttl ?? self::TTL_SECONDS,
            $callback,
        );
    }

    public static function key(string $base): string
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);

        return "catalog:v{$version}:{$base}";
    }

    public static function flush(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::put(self::VERSION_KEY, 1);
        }

        Cache::increment(self::VERSION_KEY);
    }
}
