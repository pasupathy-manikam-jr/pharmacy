<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derived cache of the stock_movements ledger; only StockLedger writes it.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $batch_id
 * @property int $qty
 * @property-read Batch $batch
 */
#[Fillable(['branch_id', 'batch_id', 'qty'])]
class StockLevel extends Model
{
    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
