<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of stock. Call inside a DB transaction: rows are locked for update.
 */
class StockLedger
{
    public function move(int $branchId, Batch $batch, int $delta, MovementType $type, ?Model $reference = null, ?int $userId = null, ?string $note = null, ?string $reason = null): StockMovement
    {
        StockLevel::query()->firstOrCreate(['branch_id' => $branchId, 'batch_id' => $batch->id], ['qty' => 0]);

        $level = StockLevel::query()
            ->where('branch_id', $branchId)
            ->where('batch_id', $batch->id)
            ->lockForUpdate()
            ->firstOrFail();

        $after = $level->qty + $delta;

        if ($after < 0) {
            throw ValidationException::withMessages([
                'stock' => "Insufficient stock for batch {$batch->batch_no} ({$level->qty} left).",
            ]);
        }

        $level->update(['qty' => $after]);

        return StockMovement::query()->create([
            'branch_id' => $branchId,
            'batch_id' => $batch->id,
            'qty_delta' => $delta,
            'qty_after' => $after,
            'type' => $type,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'user_id' => $userId,
        ]);
    }

    /**
     * First-expiry-first-out across unexpired batches. Expiring today counts as expired.
     *
     * @return list<array{batch: Batch, qty: int}>
     */
    public function allocate(int $branchId, Product $product, int $qty): array
    {
        $levels = StockLevel::query()
            ->select('stock_levels.*')
            ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
            ->where('stock_levels.branch_id', $branchId)
            ->where('stock_levels.qty', '>', 0)
            ->where('batches.product_id', $product->id)
            ->whereDate('batches.expiry_date', '>', Carbon::today())
            ->orderBy('batches.expiry_date')
            ->orderBy('batches.id')
            ->lockForUpdate()
            ->with('batch')
            ->get();

        $allocation = [];
        $remaining = $qty;

        foreach ($levels as $level) {
            if ($remaining === 0) {
                break;
            }
            $take = min($remaining, $level->qty);
            $allocation[] = ['batch' => $level->batch, 'qty' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'stock' => "Not enough unexpired stock of {$product->name}: ".($qty - $remaining)." available, {$qty} requested.",
            ]);
        }

        return $allocation;
    }

    public function onHand(int $branchId, int $productId): int
    {
        return (int) StockLevel::query()
            ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
            ->where('stock_levels.branch_id', $branchId)
            ->where('batches.product_id', $productId)
            ->sum('stock_levels.qty');
    }
}
