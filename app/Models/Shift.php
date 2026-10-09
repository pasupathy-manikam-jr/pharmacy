<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A cashier's drawer session: opening float in, counted cash out.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $user_id
 * @property int $opening_float_sen
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property int|null $expected_cash_sen
 * @property int|null $counted_cash_sen
 * @property string|null $note
 * @property-read User $user
 */
#[Fillable(['branch_id', 'user_id', 'opening_float_sen', 'opened_at', 'closed_at', 'expected_cash_sen', 'counted_cash_sen', 'note'])]
class Shift extends Model
{
    public $timestamps = false;

    public static function openFor(User $user): ?self
    {
        return self::query()->where('user_id', $user->id)->where('branch_id', $user->branch_id)->whereNull('closed_at')->latest('id')->first();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cash that should be in the drawer: float + cash sales − cash refunds + cash account payments.
     *
     * @return array{float: int, sales: int, refunds: int, payments: int, expected: int}
     */
    public function cashSummary(): array
    {
        $sales = (int) Sale::query()->where('shift_id', $this->id)->where('payment_method', 'cash')->sum('total_sen');
        $refunds = (int) DB::table('refunds')
            ->join('sales', 'sales.id', '=', 'refunds.sale_id')
            ->where('refunds.shift_id', $this->id)
            ->where('sales.payment_method', 'cash')
            ->sum('refunds.amount_sen');
        $payments = (int) CustomerPayment::query()->where('shift_id', $this->id)->where('method', 'cash')->sum('amount_sen');

        return [
            'float' => $this->opening_float_sen,
            'sales' => $sales,
            'refunds' => $refunds,
            'payments' => $payments,
            'expected' => $this->opening_float_sen + $sales - $refunds + $payments,
        ];
    }

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
