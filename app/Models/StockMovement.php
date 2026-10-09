<?php

namespace App\Models;

use App\Concerns\AppendOnly;
use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $batch_id
 * @property int $qty_delta
 * @property int $qty_after
 * @property MovementType $type
 * @property string|null $reason
 * @property-read Batch $batch
 */
#[Fillable(['branch_id', 'batch_id', 'qty_delta', 'qty_after', 'type', 'reason', 'reference_type', 'reference_id', 'note', 'user_id'])]
class StockMovement extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    protected function casts(): array
    {
        return ['type' => MovementType::class];
    }
}
