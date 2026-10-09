<?php

namespace App\Concerns;

use LogicException;

/**
 * Ledger/register rows are never edited or deleted; corrections are new reversing rows.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException(static::class.' is append-only.'));
        static::deleting(fn () => throw new LogicException(static::class.' is append-only.'));
    }
}
