<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $refund_id
 * @property int $sale_line_id
 * @property int $qty
 * @property int $amount_sen
 * @property-read SaleLine $saleLine
 */
#[Fillable(['refund_id', 'sale_line_id', 'qty', 'amount_sen'])]
class RefundLine extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<SaleLine, $this> */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }
}
