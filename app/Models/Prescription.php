<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $branch_id
 * @property int $customer_id
 * @property string $prescriber_name
 * @property string|null $prescriber_reg_no
 * @property string|null $clinic
 * @property string|null $diagnosis
 * @property Carbon $issued_on
 * @property int $refills_allowed
 * @property-read Customer $customer
 * @property-read int|null $sales_count
 */
#[Fillable(['branch_id', 'customer_id', 'prescriber_name', 'prescriber_reg_no', 'clinic', 'diagnosis', 'issued_on', 'refills_allowed'])]
class Prescription extends Model
{
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    protected function casts(): array
    {
        return ['issued_on' => 'date:Y-m-d'];
    }
}
