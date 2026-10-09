<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $batch_id
 * @property int $qty
 * @property int $cost_sen
 * @property-read Batch $batch
 */
#[Fillable(['goods_receipt_id', 'batch_id', 'qty', 'cost_sen'])]
class GoodsReceiptLine extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
