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
 * @property string $number
 * @property string $status draft|ordered|received|cancelled
 * @property Carbon|null $expected_on
 * @property string|null $note
 * @property int $user_id
 * @property Carbon $created_at
 * @property-read Supplier $supplier
 * @property-read Branch $branch
 * @property-read User $user
 */
#[Fillable(['branch_id', 'supplier_id', 'number', 'status', 'expected_on', 'note', 'user_id'])]
class PurchaseOrder extends Model
{
    public const STATUSES = ['draft', 'ordered', 'received', 'cancelled'];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    protected function casts(): array
    {
        return ['expected_on' => 'date:Y-m-d'];
    }
}
