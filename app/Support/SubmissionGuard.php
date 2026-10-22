<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * One-shot guard for forms that create a record.
 *
 * A slow page plus an impatient second click sends the POST twice, and both
 * requests pass validation and insert — two identical complaints/reports. Each
 * rendered form carries a one-time submission token (see the <x-submit-token />
 * component); the first request to reach the guard claims it atomically and the
 * repeat is turned away before it can write anything.
 */
class SubmissionGuard
{
    private const TTL_SECONDS = 900;

    /**
     * Claim the token for this submission. False means an earlier request
     * already claimed it, i.e. this POST is a duplicate.
     */
    public static function claim(string $scope, ?string $token): bool
    {
        $key = self::key($scope, $token);

        // No token at all (a form that predates the component, or a client that
        // stripped it) — nothing to compare against, so let the write through.
        if ($key === null) {
            return true;
        }

        return Cache::add($key, ['status' => 'claimed'], self::TTL_SECONDS);
    }

    /**
     * Record what the winning request created, so a duplicate can be told
     * which record already exists instead of a vague "already submitted".
     */
    public static function remember(string $scope, ?string $token, string $reference): void
    {
        $key = self::key($scope, $token);

        if ($key !== null) {
            Cache::put($key, ['status' => 'stored', 'reference' => $reference], self::TTL_SECONDS);
        }
    }

    /**
     * The reference stored by the winning request, when it has finished
     * writing. Null while it is still in flight.
     */
    public static function reference(string $scope, ?string $token): ?string
    {
        $key = self::key($scope, $token);
        $entry = $key === null ? null : Cache::get($key);

        return is_array($entry) ? ($entry['reference'] ?? null) : null;
    }

    private static function key(string $scope, ?string $token): ?string
    {
        $token = trim((string) $token);

        return $token === '' ? null : 'submission-guard:'.$scope.':'.sha1($token);
    }
}
