<?php

namespace App\Models;

use App\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $product_id
 * @property string $register
 * @property int $sale_line_id
 * @property int $qty
 * @property int $balance_after
 * @property string $customer_name
 * @property int|null $reverses_id
 * @property Carbon $created_at
 * @property-read Product $product
 * @property-read Batch $batch
 * @property-read User $pharmacist
 */
#[Fillable(['branch_id', 'register', 'sale_line_id', 'product_id', 'batch_id', 'qty', 'balance_after', 'customer_name', 'customer_ic', 'customer_address', 'prescriber', 'dosage', 'pharmacist_id', 'reverses_id'])]
class PoisonRegisterEntry extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

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

    /** @return BelongsTo<User, $this> */
    public function pharmacist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pharmacist_id');
    }
}
