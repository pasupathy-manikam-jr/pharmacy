<?php

namespace App\Models;

use App\Enums\PoisonGroup;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
 * @property string|null $image_path
 * @property-read string|null $image_url
 */
#[Fillable(['name', 'generic_name', 'strength', 'form', 'poison_group', 'barcode', 'mal_reg_no', 'image_path', 'unit', 'price_sen', 'tax_rate_bp', 'reorder_level', 'is_active'])]
#[Appends(['image_url'])]
class Product extends Model
{
    public static function urlFor(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** @return Attribute<string|null, never> */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => self::urlFor($this->image_path));
    }

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
