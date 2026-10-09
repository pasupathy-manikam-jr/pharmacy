<?php

namespace App\Support;

/**
 * Rows per page for every paginated list, from `?per_page=`; anything else falls back to the default.
 */
class PerPage
{
    public const OPTIONS = [10, 20, 50, 100];

    public const DEFAULT = 20;

    public static function get(): int
    {
        $value = (int) request()->query('per_page', (string) self::DEFAULT);

        return in_array($value, self::OPTIONS, true) ? $value : self::DEFAULT;
    }
}
