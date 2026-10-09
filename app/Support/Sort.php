<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Column sorting for list pages from `?sort=key&dir=asc|desc`. Only whitelisted keys are accepted,
 * so the mapped SQL expressions are never user-controlled.
 */
class Sort
{
    /**
     * @param  array<string, literal-string>  $columns  sort key => SQL column or expression
     * @param  'asc'|'desc'  $defaultDir
     * @return array{sort: string, dir: string} what the page should show as active
     */
    public static function apply(Builder $query, array $columns, string $default, string $defaultDir = 'asc', ?string $tieBreaker = null): array
    {
        $key = request()->query('sort');
        $dir = request()->query('dir') === 'desc' ? 'desc' : 'asc';

        if (! is_string($key) || ! isset($columns[$key])) {
            $key = $default;
            $dir = $defaultDir;
        }

        $query->orderByRaw("{$columns[$key]} {$dir}");
        if ($tieBreaker) {
            $query->orderBy($tieBreaker, $dir);
        }

        return ['sort' => $key, 'dir' => $dir];
    }
}
