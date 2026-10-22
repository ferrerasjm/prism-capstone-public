<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for the officer/coordinator report pages. Their payloads are
 * several aggregate queries deep and only change when a case (or its offenses)
 * changes, so we cache the computed payload and bump a shared version counter on
 * every write - old entries fall out on their own via the TTL.
 *
 * Every method is failure-tolerant: if the cache backend is unavailable the
 * report still renders, just without the speed-up. Nothing here is allowed to
 * break a page load or a case save.
 */
final class ReportCache
{
    /** Seconds a cached report payload stays warm before it is recomputed anyway. */
    private const TTL = 600;

    private const VERSION_KEY = 'reports:cache-version';

    /**
     * Return the cached payload for $key, or run $callback and cache it. The
     * effective cache key folds in the current version counter, so a bump()
     * makes every previously cached report a miss without enumerating keys.
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        $qualified = self::qualify($key);

        try {
            $cached = Cache::get($qualified);

            // A cache entry serialized under a class shape that no longer matches
            // (a renamed/restructured model or Collection) unserializes into
            // __PHP_Incomplete_Class objects instead of throwing, so a plain
            // Cache::remember() would silently hand the view broken data. Treat
            // that the same as a miss and recompute.
            if ($cached !== null && ! self::isCorrupted($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $value = $callback();

        try {
            Cache::put($qualified, $value, self::TTL);
        } catch (\Throwable $e) {
            report($e);
        }

        return $value;
    }

    /**
     * Recursively checks for __PHP_Incomplete_Class objects left behind by a
     * failed unserialize(). Bounded depth keeps this cheap and avoids touching
     * Eloquent model relations (which could trigger lazy-loaded queries).
     */
    private static function isCorrupted(mixed $value, int $depth = 0): bool
    {
        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }

        if ($depth >= 3) {
            return false;
        }

        if ($value instanceof Collection) {
            return $value->contains(fn ($item) => self::isCorrupted($item, $depth + 1));
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::isCorrupted($item, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Invalidate every cached report. Called from model events on case writes.
     * A lost race between two concurrent bumps only means both land on the same
     * new version - still different from what is cached, so still a miss.
     */
    public static function bump(): void
    {
        try {
            $current = (int) Cache::get(self::VERSION_KEY, 0);
            Cache::forever(self::VERSION_KEY, $current + 1);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function qualify(string $key): string
    {
        $version = 0;

        try {
            $version = (int) Cache::get(self::VERSION_KEY, 0);
        } catch (\Throwable $e) {
            report($e);
        }

        return "reports:v{$version}:{$key}";
    }
}
