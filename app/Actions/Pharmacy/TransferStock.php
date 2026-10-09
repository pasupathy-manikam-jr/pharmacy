<?php

namespace App\Actions\Pharmacy;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move batches to another branch: one ledger move out, one in, same transaction, same batch (expiry travels with it).
 */
class TransferStock
{
    public function __construct(private StockLedger $ledger) {}

    /**
     * @param  list<array{batch_id: int, qty: int}>  $lines
     */
    public function handle(User $user, Branch $to, array $lines, ?string $note = null): void
    {
        if ($to->id === $user->branch_id) {
            throw ValidationException::withMessages(['to_branch_id' => __('Choose a different branch.')]);
        }

        DB::transaction(function () use ($user, $to, $lines, $note) {
            foreach ($lines as $line) {
                $batch = Batch::query()->findOrFail($line['batch_id']);
                $this->ledger->move((int) $user->branch_id, $batch, -$line['qty'], MovementType::TransferOut, $to, $user->id, $note);
                $this->ledger->move($to->id, $batch, $line['qty'], MovementType::TransferIn, $user->branch, $user->id, $note);
            }

            AuditLog::record('stock.transferred', $to, ['lines' => $lines, 'note' => $note]);
        });
    }
}
