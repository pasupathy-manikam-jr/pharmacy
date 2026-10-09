<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $batch_no
 * @property Carbon $expiry_date
 * @property int $cost_sen
 * @property int|null $supplier_id
 * @property-read Product $product
 */
#[Fillable(['product_id', 'batch_no', 'expiry_date', 'cost_sen', 'supplier_id'])]
class Batch extends Model
{
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<StockLevel, $this> */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    protected function casts(): array
    {
        return ['expiry_date' => 'date:Y-m-d'];
    }
}
