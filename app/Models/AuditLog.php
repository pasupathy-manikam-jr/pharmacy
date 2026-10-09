<?php

namespace App\Models;

use App\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $branch_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $data
 * @property string|null $ip
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable(['branch_id', 'user_id', 'action', 'subject_type', 'subject_id', 'data', 'ip'])]
class AuditLog extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    /**
     * Record who did what. Called from actions and controllers at the point the change is committed.
     *
     * @param  array<string, mixed>  $data
     */
    public static function record(string $action, ?Model $subject = null, array $data = []): self
    {
        $user = auth()->user();

        return self::query()->create([
            'branch_id' => $user instanceof User ? $user->branch_id : null,
            'user_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'data' => $data ?: null,
            'ip' => request()->ip(),
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
