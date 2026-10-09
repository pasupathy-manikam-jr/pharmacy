<?php

namespace App\Models;

use App\Support\EInvoiceBuilder;
use EInvoiceSdk\Concerns\HasEInvoices;
use EInvoiceSdk\Contracts\EInvoiceable;
use EInvoiceSdk\Data\Document;
use EInvoiceSdk\Data\Party;
use EInvoiceSdk\Enums\DocumentType;
use EInvoiceSdk\Enums\Status;
use EInvoiceSdk\Exceptions\EInvoiceException;
use EInvoiceSdk\Models\EInvoiceDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $branch_id
 * @property int|null $shift_id
 * @property int $user_id
 * @property string $number
 * @property int $amount_sen
 * @property string $reason
 * @property Carbon $created_at
 * @property-read Sale $sale
 * @property-read User $user
 */
#[Fillable(['sale_id', 'branch_id', 'shift_id', 'user_id', 'number', 'amount_sen', 'reason'])]
class Refund extends Model implements EInvoiceable
{
    use HasEInvoices;

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Refund note against the sale's validated individual e-invoice. Refunds on consolidated sales need none:
     * the consolidated batch is built from net amounts.
     */
    public function toEInvoiceDocument(): Document
    {
        $this->loadMissing(['sale.branch', 'sale.customer', 'lines.saleLine.product']);

        /** @var EInvoiceDocument|null $original */
        $original = $this->sale->einvoiceDocuments()->where('status', Status::Valid)->latest('id')->first();
        if (! $original) {
            throw new EInvoiceException(__('Only refunds on a sale with a validated e-invoice need a refund note.'));
        }

        $parts = [];
        $items = [];
        foreach ($this->lines as $line) {
            $sl = $line->saleLine;
            $tax = $sl->qty ? intdiv($sl->tax_sen * $line->qty, $sl->qty) : 0;
            $part = ['taxable_sen' => $line->amount_sen - $tax, 'tax_sen' => $tax, 'rate_bp' => $sl->product->tax_rate_bp];
            $parts[] = $part;
            $unit = intdiv($line->amount_sen - $tax, max(1, $line->qty));
            $items[] = EInvoiceBuilder::line(trim($sl->product->name.' '.$sl->product->strength), $line->qty, $unit, $unit * $line->qty - ($line->amount_sen - $tax), EInvoiceBuilder::taxes([$part]));
        }

        $c = $this->sale->customer;
        $buyer = $c?->tin
            ? new Party(name: $c->name, tin: $c->tin, brn: $c->brn, nric: $c->brn ? null : $c->ic_no, email: $c->email, phone: $c->phone, addressLine1: $c->address)
            : EInvoiceBuilder::generalPublic();

        return new Document(
            type: DocumentType::RefundNote,
            number: $this->number,
            issuedAt: $this->created_at,
            supplier: EInvoiceBuilder::supplier($this->sale->branch),
            buyer: $buyer,
            lines: $items,
            taxes: EInvoiceBuilder::taxes($parts),
            subtotal: collect($parts)->sum('taxable_sen') / 100,
            grandTotal: $this->amount_sen / 100,
            originalNumber: $this->sale->number,
            originalUuid: $original->uuid,
        );
    }

    /** @return HasMany<RefundLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(RefundLine::class);
    }
}
