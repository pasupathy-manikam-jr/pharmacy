<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $purchase_order_id
 * @property int $qty
 * @property int $cost_sen
 * @property-read Product $product
 */
#[Fillable(['purchase_order_id', 'product_id', 'qty', 'cost_sen'])]
class PurchaseOrderLine extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
