<?php

namespace App\Actions\Pharmacy;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\PoisonRegisterEntry;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Refund some or all units of a sale: stock back to the same batches, reversing register rows,
 * money paid back in the sale's original tender.
 */
class RefundSale
{
    public function __construct(private StockLedger $ledger) {}

    /**
     * @param  array<int, int>  $quantities  sale_line_id => qty to refund
     */
    public function handle(User $user, Sale $sale, array $quantities, string $reason): Refund
    {
        return DB::transaction(function () use ($user, $sale, $quantities, $reason) {
            $sale = Sale::query()->lockForUpdate()->with('lines.batch')->findOrFail($sale->id);
            $quantities = array_filter($quantities, fn ($q) => $q > 0);

            if (! $quantities) {
                throw ValidationException::withMessages(['lines' => 'Choose at least one item to refund.']);
            }

            $shift = Shift::openFor($user);
            if ($sale->payment_method === 'cash' && ! $shift) {
                throw ValidationException::withMessages(['shift' => 'Open a shift to pay out a cash refund.']);
            }

            $entries = PoisonRegisterEntry::query()
                ->whereIn('sale_line_id', array_keys($quantities))
                ->whereNull('reverses_id')
                ->get()
                ->keyBy('sale_line_id');

            if ($entries->isNotEmpty() && ! $user->isPharmacist()) {
                throw ValidationException::withMessages(['lines' => 'Only a pharmacist can refund scheduled poisons.']);
            }

            $net = $sale->lineNetTotals();
            $refund = Refund::query()->create([
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'shift_id' => $shift?->id,
                'user_id' => $user->id,
                'number' => uniqid('tmp', true),
                'amount_sen' => 0,
                'reason' => $reason,
            ]);

            $total = 0;
            foreach ($quantities as $lineId => $qty) {
                /** @var SaleLine|null $line */
                $line = $sale->lines->firstWhere('id', $lineId);
                if (! $line) {
                    throw ValidationException::withMessages(['lines' => 'That item is not on this sale.']);
                }
                if ($qty > $line->qty - $line->refunded_qty) {
                    throw ValidationException::withMessages(["lines.$lineId" => 'Only '.($line->qty - $line->refunded_qty)." of {$line->product->name} left to refund."]);
                }

                // Cumulative rounding: refunding every unit pays back exactly the line's net total.
                $before = intdiv($net[$line->id] * $line->refunded_qty, $line->qty);
                $after = intdiv($net[$line->id] * ($line->refunded_qty + $qty), $line->qty);
                $amount = $after - $before;

                $refund->lines()->create(['sale_line_id' => $line->id, 'qty' => $qty, 'amount_sen' => $amount]);
                $line->update(['refunded_qty' => $line->refunded_qty + $qty]);
                $this->ledger->move($sale->branch_id, $line->batch, $qty, MovementType::Refund, $refund, $user->id);
                $total += $amount;

                if ($entry = $entries->get($line->id)) {
                    PoisonRegisterEntry::query()->create([
                        ...$entry->only(['branch_id', 'register', 'sale_line_id', 'product_id', 'batch_id', 'customer_name', 'customer_ic', 'customer_address', 'prescriber', 'dosage']),
                        'qty' => -$qty,
                        'balance_after' => $this->ledger->onHand($sale->branch_id, $entry->product_id),
                        'pharmacist_id' => $user->id,
                        'reverses_id' => $entry->id,
                    ]);
                }
            }

            $refund->update(['number' => sprintf('RF%s-%06d', now()->format('ymd'), $refund->id), 'amount_sen' => $total]);

            $fully = $sale->lines->every(fn (SaleLine $l) => $l->refunded_qty >= $l->qty);
            $sale->update(['status' => $fully ? 'refunded' : 'partially_refunded', 'refunded_at' => now()]);

            AuditLog::record('sale.refunded', $sale, ['refund' => $refund->number, 'amount_sen' => $total, 'reason' => $reason]);

            return $refund;
        });
    }
}
