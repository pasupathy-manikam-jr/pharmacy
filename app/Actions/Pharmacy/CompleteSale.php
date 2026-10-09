<?php

namespace App\Actions\Pharmacy;

use App\Enums\MovementType;
use App\Enums\PoisonGroup;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PoisonRegisterEntry;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every sale path goes through here: open shift, server-side prices, FEFO batches, poison rules, register rows.
 */
class CompleteSale
{
    public function __construct(private StockLedger $ledger) {}

    /**
     * @param  array{
     *     customer_id?: int|null,
     *     customer?: array{name: string, ic_no?: string|null, address?: string|null}|null,
     *     prescription_id?: int|null,
     *     prescription?: array{prescriber_name: string, prescriber_reg_no?: string|null, clinic?: string|null, diagnosis?: string|null, issued_on: string, refills_allowed?: int|null}|null,
     *     lines: list<array{product_id: int, qty: int, dosage?: string|null}>,
     *     discount_sen?: int|null,
     *     payment_method: string,
     *     tendered_sen?: int|null,
     * }  $data
     */
    public function handle(User $user, array $data): Sale
    {
        $branchId = (int) $user->branch_id;

        return DB::transaction(function () use ($user, $data, $branchId) {
            $shift = Shift::openFor($user);
            if (! $shift) {
                throw ValidationException::withMessages(['shift' => 'Open a shift before selling.']);
            }

            $products = Product::query()->whereIn('id', array_column($data['lines'], 'product_id'))->get()->keyBy('id');
            $groups = $products->map(fn (Product $p) => $p->poison_group);
            $hasPoison = $groups->contains(fn (PoisonGroup $g) => $g !== PoisonGroup::None);
            $needsRx = $groups->contains(fn (PoisonGroup $g) => $g->requiresPrescription());

            if ($hasPoison && ! $user->isPharmacist()) {
                throw ValidationException::withMessages(['lines' => 'Scheduled poisons can only be sold by a pharmacist.']);
            }

            $customer = $this->resolveCustomer($data);

            if ($hasPoison && ! $customer) {
                throw ValidationException::withMessages(['customer.name' => 'Customer name is required for scheduled poisons.']);
            }

            if ($data['payment_method'] === 'credit' && ! $customer) {
                throw ValidationException::withMessages(['payment_method' => 'Choose the customer whose account this goes on.']);
            }

            $prescription = $needsRx ? $this->resolvePrescription($data, $customer, $branchId) : null;

            $sale = Sale::query()->create([
                'branch_id' => $branchId,
                'shift_id' => $shift->id,
                'number' => uniqid('tmp', true),
                'customer_id' => $customer?->id,
                'prescription_id' => $prescription?->id,
                'user_id' => $user->id,
                'pharmacist_id' => $user->isPharmacist() ? $user->id : null,
                'subtotal_sen' => 0,
                'total_sen' => 0,
                'payment_method' => $data['payment_method'],
                'tendered_sen' => 0,
            ]);

            $subtotal = 0;
            $tax = 0;

            foreach ($data['lines'] as $input) {
                $product = $products[$input['product_id']];

                foreach ($this->ledger->allocate($branchId, $product, $input['qty']) as ['batch' => $batch, 'qty' => $qty]) {
                    $lineTax = intdiv($product->price_sen * $qty * $product->tax_rate_bp + 5000, 10000);
                    $line = $sale->lines()->create([
                        'product_id' => $product->id,
                        'batch_id' => $batch->id,
                        'qty' => $qty,
                        'price_sen' => $product->price_sen,
                        'tax_sen' => $lineTax,
                        'dosage' => $input['dosage'] ?? null,
                    ]);
                    $subtotal += $product->price_sen * $qty;
                    $tax += $lineTax;

                    $this->ledger->move($branchId, $batch, -$qty, MovementType::Sale, $sale, $user->id);

                    if ($register = $product->poison_group->register()) {
                        /** @var Customer $customer */
                        PoisonRegisterEntry::query()->create([
                            'branch_id' => $branchId,
                            'register' => $register,
                            'sale_line_id' => $line->id,
                            'product_id' => $product->id,
                            'batch_id' => $batch->id,
                            'qty' => $qty,
                            'balance_after' => $this->ledger->onHand($branchId, $product->id),
                            'customer_name' => $customer->name,
                            'customer_ic' => $customer->ic_no,
                            'customer_address' => $customer->address,
                            'prescriber' => $prescription ? trim("{$prescription->prescriber_name} {$prescription->prescriber_reg_no}") : null,
                            'dosage' => $input['dosage'] ?? null,
                            'pharmacist_id' => $user->id,
                        ]);
                    }
                }
            }

            $discount = (int) ($data['discount_sen'] ?? 0);
            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['discount_sen' => 'Discount exceeds subtotal.']);
            }
            $total = $subtotal + $tax - $discount;

            $tendered = $data['payment_method'] === 'cash' ? (int) ($data['tendered_sen'] ?? 0) : $total;
            if ($tendered < $total) {
                throw ValidationException::withMessages(['tendered_sen' => 'Amount tendered is less than the total.']);
            }

            $sale->update([
                'number' => sprintf('INV%s-%06d', now()->format('ymd'), $sale->id),
                'subtotal_sen' => $subtotal,
                'discount_sen' => $discount,
                'tax_sen' => $tax,
                'total_sen' => $total,
                'tendered_sen' => $tendered,
            ]);

            if ($discount > 0 || $hasPoison) {
                AuditLog::record('sale.completed', $sale, array_filter(['discount_sen' => $discount ?: null, 'poison' => $hasPoison ?: null]));
            }

            return $sale;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): ?Customer
    {
        if (is_numeric($data['customer_id'] ?? null)) {
            return Customer::query()->findOrFail((int) $data['customer_id']);
        }

        /** @var array{name?: string, ic_no?: string|null, address?: string|null}|null $new */
        $new = $data['customer'] ?? null;

        if (empty($new['name'])) {
            return null;
        }

        return ! empty($new['ic_no'])
            ? Customer::query()->firstOrCreate(['ic_no' => $new['ic_no']], $new)
            : Customer::query()->create($new);
    }

    /**
     * A new prescription, or a refill of an earlier one: total dispenses ≤ 1 + refills allowed.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePrescription(array $data, ?Customer $customer, int $branchId): Prescription
    {
        /** @var Customer $customer */
        if (is_numeric($data['prescription_id'] ?? null)) {
            $prescription = Prescription::query()->where('customer_id', $customer->id)->lockForUpdate()->findOrFail((int) $data['prescription_id']);
            $dispensed = Sale::query()->where('prescription_id', $prescription->id)->where('status', '!=', 'refunded')->count();

            if ($dispensed >= 1 + $prescription->refills_allowed) {
                throw ValidationException::withMessages(['prescription_id' => "This prescription has been fully dispensed ({$dispensed} of ".(1 + $prescription->refills_allowed).').']);
            }

            return $prescription;
        }

        /** @var array<string, mixed>|null $rx */
        $rx = $data['prescription'] ?? null;
        if (empty($rx['prescriber_name'])) {
            throw ValidationException::withMessages(['prescription.prescriber_name' => 'A prescription is required for this item.']);
        }

        return Prescription::query()->create([
            ...$rx,
            'refills_allowed' => (int) ($rx['refills_allowed'] ?? 0),
            'customer_id' => $customer->id,
            'branch_id' => $branchId,
        ]);
    }
}
