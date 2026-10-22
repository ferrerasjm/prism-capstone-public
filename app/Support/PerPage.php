<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rows per page for the paginated tables, read from the query string.
 *
 * The value is whitelisted rather than cast, because an unbounded per_page is a cheap way
 * to ask the server to build one page holding every row in the table.
 */
final class PerPage
{
    /** @var list<int> */
    public const OPTIONS = [10, 25, 50, 100];

    public const DEFAULT = 10;

    public static function resolve(string $key = 'per_page'): int
    {
        $requested = request()->query($key);

        if (! is_scalar($requested)) {
            return self::DEFAULT;
        }

        // Compared as a string first, so trailing junk is rejected outright rather than
        // being quietly cast away - "25; drop table users" is not a request for 25.
        foreach (self::OPTIONS as $option) {
            if ((string) $requested === (string) $option) {
                return $option;
            }
        }

        return self::DEFAULT;
    }
}
