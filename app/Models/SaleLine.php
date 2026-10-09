<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $product_id
 * @property int $batch_id
 * @property int $qty
 * @property int $price_sen
 * @property int $tax_sen
 * @property string|null $dosage
 * @property int $refunded_qty
 * @property-read Product $product
 * @property-read Batch $batch
 */
#[Fillable(['sale_id', 'product_id', 'batch_id', 'qty', 'price_sen', 'tax_sen', 'dosage', 'refunded_qty'])]
class SaleLine extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
