<?php

namespace App\Actions\Pharmacy;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Stock take corrections and write-offs. Every change is a ledger movement with a reason.
 */
class AdjustStock
{
    public const REASONS = [
        'count' => 'Stock take correction',
        'damaged' => 'Damaged or broken',
        'expired' => 'Expired, written off',
        'supplier_return' => 'Returned to supplier',
        'other' => 'Other',
    ];

    public function __construct(private StockLedger $ledger) {}

    public function handle(User $user, Batch $batch, int $delta, string $reason, ?string $note = null): ?StockMovement
    {
        if ($delta === 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $batch, $delta, $reason, $note) {
            $movement = $this->ledger->move((int) $user->branch_id, $batch, $delta, MovementType::Adjustment, null, $user->id, $note, $reason);

            AuditLog::record('stock.adjusted', $batch, ['delta' => $delta, 'reason' => $reason, 'note' => $note]);

            return $movement;
        });
    }
}
