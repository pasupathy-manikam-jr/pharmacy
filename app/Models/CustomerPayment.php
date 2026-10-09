<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money a customer pays against their account (credit sales).
 *
 * @property int $id
 * @property int $customer_id
 * @property int $amount_sen
 * @property string $method
 * @property string|null $reference
 * @property Carbon $created_at
 * @property-read User $user
 */
#[Fillable(['customer_id', 'branch_id', 'shift_id', 'user_id', 'amount_sen', 'method', 'reference'])]
class CustomerPayment extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
