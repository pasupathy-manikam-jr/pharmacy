<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $ic_no
 * @property Carbon|null $dob
 * @property string|null $sex
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $citizenship
 * @property string|null $allergies
 * @property string|null $tin
 * @property string|null $brn
 * @property string|null $email
 */
#[Fillable(['name', 'ic_no', 'dob', 'sex', 'phone', 'address', 'citizenship', 'allergies', 'tin', 'brn', 'email'])]
class Customer extends Model
{
    /**
     * What the customer owes: credit sales, less refunds on them, less payments received.
     */
    public function balanceSen(): int
    {
        $sales = (int) Sale::query()->where('customer_id', $this->id)->where('payment_method', 'credit')->sum('total_sen');
        $refunds = (int) Refund::query()->whereHas('sale', fn ($q) => $q->where('customer_id', $this->id)->where('payment_method', 'credit'))->sum('amount_sen');
        $payments = (int) CustomerPayment::query()->where('customer_id', $this->id)->sum('amount_sen');

        return $sales - $refunds - $payments;
    }

    protected function casts(): array
    {
        return ['dob' => 'date:Y-m-d'];
    }
}
