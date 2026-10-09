<?php

namespace App\Models;

use App\Support\EInvoiceBuilder;
use EInvoiceSdk\Concerns\HasEInvoices;
use EInvoiceSdk\Contracts\EInvoiceable;
use EInvoiceSdk\Data\Document;
use EInvoiceSdk\Enums\DocumentType;
use EInvoiceSdk\Enums\Status;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One month of walk-in sales for a branch, sent to LHDN as a single consolidated e-invoice.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $period YYYY-MM
 * @property int $sale_count
 * @property int $subtotal_sen
 * @property int $tax_sen
 * @property int $total_sen
 * @property Carbon $created_at
 * @property-read Branch $branch
 */
#[Fillable(['branch_id', 'period', 'sale_count', 'subtotal_sen', 'tax_sen', 'total_sen'])]
class ConsolidatedEinvoice extends Model implements EInvoiceable
{
    use HasEInvoices;

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Sales in the period not covered by their own submitted or validated e-invoice.
     *
     * @return Builder<Sale>
     */
    public static function salesFor(int $branchId, string $period): Builder
    {
        $start = Carbon::createFromFormat('Y-m', $period)?->startOfMonth() ?? now()->startOfMonth();

        return Sale::query()
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $start->copy()->endOfMonth()])
            ->whereDoesntHave('einvoiceDocuments', fn ($q) => $q->whereIn('status', [Status::Submitted, Status::Valid, Status::Pending]))
            ->with(['lines.product', 'refunds']);
    }

    /**
     * Snapshot the month's net totals (after refunds).
     */
    public function recalculate(): self
    {
        $subtotal = 0;
        $tax = 0;
        $count = 0;

        foreach (self::salesFor($this->branch_id, $this->period)->get() as $sale) {
            $net = $sale->total_sen - (int) $sale->refunds->sum('amount_sen');
            if ($net <= 0) {
                continue;
            }
            $saleTax = $sale->total_sen ? intdiv($sale->tax_sen * $net, $sale->total_sen) : 0;
            $tax += $saleTax;
            $subtotal += $net - $saleTax;
            $count++;
        }

        $this->fill(['sale_count' => $count, 'subtotal_sen' => $subtotal, 'tax_sen' => $tax, 'total_sen' => $subtotal + $tax])->save();

        return $this;
    }

    public function toEInvoiceDocument(): Document
    {
        $this->loadMissing('branch');

        $parts = [];
        $items = [];
        foreach (self::salesFor($this->branch_id, $this->period)->get() as $sale) {
            $net = $sale->total_sen - (int) $sale->refunds->sum('amount_sen');
            if ($net <= 0) {
                continue;
            }
            $saleTax = $sale->total_sen ? intdiv($sale->tax_sen * $net, $sale->total_sen) : 0;
            $rate = $sale->tax_sen > 0 ? (int) ($sale->lines->max(fn (SaleLine $l) => $l->product->tax_rate_bp) ?? 0) : 0;
            $part = ['taxable_sen' => $net - $saleTax, 'tax_sen' => $saleTax, 'rate_bp' => $rate];
            $parts[] = $part;
            $items[] = EInvoiceBuilder::line("Receipt {$sale->number}", 1, $net - $saleTax, 0, EInvoiceBuilder::taxes([$part]), EInvoiceBuilder::CLASS_CONSOLIDATED);
        }

        return new Document(
            type: DocumentType::Invoice,
            number: "CON-{$this->branch_id}-{$this->period}",
            issuedAt: now(),
            supplier: EInvoiceBuilder::supplier($this->branch),
            buyer: EInvoiceBuilder::generalPublic(),
            lines: $items,
            taxes: EInvoiceBuilder::taxes($parts),
            subtotal: collect($parts)->sum('taxable_sen') / 100,
            grandTotal: collect($parts)->sum(fn ($p) => $p['taxable_sen'] + $p['tax_sen']) / 100,
            consolidated: true,
        );
    }
}
