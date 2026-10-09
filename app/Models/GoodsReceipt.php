<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $supplier_id
 * @property int|null $purchase_order_id
 * @property string|null $invoice_no
 * @property Carbon $received_on
 * @property int $total_sen
 * @property string $payment_status
 * @property-read Supplier $supplier
 */
#[Fillable(['branch_id', 'supplier_id', 'purchase_order_id', 'invoice_no', 'received_on', 'total_sen', 'payment_status', 'user_id'])]
class GoodsReceipt extends Model
{
    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<GoodsReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    protected function casts(): array
    {
        return ['received_on' => 'date:Y-m-d'];
    }
}
