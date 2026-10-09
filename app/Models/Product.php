<?php

namespace App\Models;

use App\Enums\PoisonGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $generic_name
 * @property string|null $strength
 * @property string|null $form
 * @property PoisonGroup $poison_group
 * @property string|null $barcode
 * @property string $unit
 * @property int $price_sen
 * @property int $tax_rate_bp
 * @property int $reorder_level
 * @property bool $is_active
 */
#[Fillable(['name', 'generic_name', 'strength', 'form', 'poison_group', 'barcode', 'mal_reg_no', 'unit', 'price_sen', 'tax_rate_bp', 'reorder_level', 'is_active'])]
class Product extends Model
{
    /** @return HasMany<Batch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    protected function casts(): array
    {
        return [
            'poison_group' => PoisonGroup::class,
            'is_active' => 'boolean',
        ];
    }
}
