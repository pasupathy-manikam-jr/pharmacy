<?php

namespace App\Actions\Pharmacy;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveGoods
{
    public function __construct(private StockLedger $ledger) {}

    /**
     * @param  array{supplier_id: int, purchase_order_id?: int|null, invoice_no?: string|null, received_on: string, payment_status: string, lines: list<array{product_id: int, batch_no: string, expiry_date: string, qty: int, cost_sen: int}>}  $data
     */
    public function handle(User $user, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($user, $data) {
            $receipt = GoodsReceipt::query()->create([
                'branch_id' => $user->branch_id,
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'invoice_no' => $data['invoice_no'] ?? null,
                'received_on' => $data['received_on'],
                'payment_status' => $data['payment_status'],
                'total_sen' => collect($data['lines'])->sum(fn ($l) => $l['qty'] * $l['cost_sen']),
                'user_id' => $user->id,
            ]);

            foreach ($data['lines'] as $i => $line) {
                $batch = Batch::query()->firstOrCreate(
                    ['product_id' => $line['product_id'], 'batch_no' => $line['batch_no']],
                    ['expiry_date' => $line['expiry_date'], 'cost_sen' => $line['cost_sen'], 'supplier_id' => $data['supplier_id']],
                );

                if ($batch->expiry_date->toDateString() !== $line['expiry_date']) {
                    throw ValidationException::withMessages([
                        "lines.$i.expiry_date" => "Batch {$line['batch_no']} already exists with expiry {$batch->expiry_date->toDateString()}.",
                    ]);
                }

                $receipt->lines()->create(['batch_id' => $batch->id, 'qty' => $line['qty'], 'cost_sen' => $line['cost_sen']]);
                $this->ledger->move((int) $user->branch_id, $batch, $line['qty'], MovementType::Receipt, $receipt, $user->id);
            }

            if ($receipt->purchase_order_id) {
                PurchaseOrder::query()->whereKey($receipt->purchase_order_id)->update(['status' => 'received']);
            }

            AuditLog::record('stock.received', $receipt, ['total_sen' => $receipt->total_sen, 'lines' => count($data['lines'])]);

            return $receipt;
        });
    }
}
