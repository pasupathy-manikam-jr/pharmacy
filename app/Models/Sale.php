<?php

namespace App\Models;

use App\Support\EInvoiceBuilder;
use EInvoiceSdk\Concerns\HasEInvoices;
use EInvoiceSdk\Contracts\EInvoiceable;
use EInvoiceSdk\Data\Document;
use EInvoiceSdk\Data\Party;
use EInvoiceSdk\Enums\DocumentType;
use EInvoiceSdk\Exceptions\EInvoiceException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int|null $shift_id
 * @property string $number
 * @property int|null $customer_id
 * @property int|null $prescription_id
 * @property int $user_id
 * @property int|null $pharmacist_id
 * @property int $subtotal_sen
 * @property int $discount_sen
 * @property int $tax_sen
 * @property int $total_sen
 * @property string $payment_method
 * @property int $tendered_sen
 * @property string $status completed|partially_refunded|refunded
 * @property Carbon|null $refunded_at
 * @property Carbon $created_at
 * @property-read Customer|null $customer
 * @property-read Prescription|null $prescription
 * @property-read User $user
 * @property-read Branch $branch
 */
#[Fillable(['branch_id', 'shift_id', 'number', 'customer_id', 'prescription_id', 'user_id', 'pharmacist_id', 'subtotal_sen', 'discount_sen', 'tax_sen', 'total_sen', 'payment_method', 'tendered_sen', 'status', 'refunded_at'])]
class Sale extends Model implements EInvoiceable
{
    use HasEInvoices;

    public const PAYMENT_METHODS = ['cash', 'card', 'ewallet', 'credit'];

    /** @return HasMany<SaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Prescription, $this> */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Line totals after tax and their share of the sale discount, in sen; refunds are paid from these.
     *
     * @return array<int, int> sale_line_id => net sen
     */
    public function lineNetTotals(): array
    {
        $lines = $this->lines->values();
        $gross = array_values($lines->map(fn (SaleLine $l) => $l->price_sen * $l->qty + $l->tax_sen)->all());
        $discounts = EInvoiceBuilder::allocate($this->discount_sen, $gross);

        return $lines->mapWithKeys(fn (SaleLine $l, int $i) => [$l->id => $gross[$i] - $discounts[$i]])->all();
    }

    /**
     * Individual e-invoice for a customer who asked for one (needs their TIN).
     */
    public function toEInvoiceDocument(): Document
    {
        $this->loadMissing(['lines.product', 'branch', 'customer']);

        if (! $this->customer?->tin) {
            throw new EInvoiceException('This customer has no TIN. Add it on the customer, or leave the sale for the monthly consolidated e-invoice.');
        }

        $lines = $this->lines->values();
        $discounts = EInvoiceBuilder::allocate($this->discount_sen, array_values($lines->map(fn (SaleLine $l) => $l->price_sen * $l->qty)->all()));
        $parts = [];
        $items = [];

        foreach ($lines as $i => $line) {
            $part = ['taxable_sen' => $line->price_sen * $line->qty - $discounts[$i], 'tax_sen' => $line->tax_sen, 'rate_bp' => $line->product->tax_rate_bp];
            $parts[] = $part;
            $items[] = EInvoiceBuilder::line(trim($line->product->name.' '.$line->product->strength), $line->qty, $line->price_sen, $discounts[$i], EInvoiceBuilder::taxes([$part]));
        }

        $c = $this->customer;

        return new Document(
            type: DocumentType::Invoice,
            number: $this->number,
            issuedAt: $this->created_at,
            supplier: EInvoiceBuilder::supplier($this->branch),
            buyer: new Party(name: $c->name, tin: $c->tin, brn: $c->brn, nric: $c->brn ? null : $c->ic_no, email: $c->email, phone: $c->phone, addressLine1: $c->address),
            lines: $items,
            taxes: EInvoiceBuilder::taxes($parts),
            subtotal: ($this->subtotal_sen - $this->discount_sen) / 100,
            grandTotal: $this->total_sen / 100,
            paymentMode: match ($this->payment_method) {
                'cash' => '01',
                'card' => '04',
                'ewallet' => '06',
                default => null,
            },
        );
    }

    protected function casts(): array
    {
        return ['refunded_at' => 'datetime'];
    }
}
