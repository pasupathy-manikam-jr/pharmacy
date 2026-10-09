<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $licence_no
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $company_name
 * @property string|null $tin
 * @property string|null $brn
 * @property string|null $sst_no
 * @property string $msic_code
 * @property string|null $email
 * @property string|null $postcode
 * @property string|null $city
 * @property string|null $state
 */
#[Fillable(['name', 'company_name', 'licence_no', 'address', 'phone', 'tin', 'brn', 'sst_no', 'msic_code', 'email', 'postcode', 'city', 'state'])]
class Branch extends Model
{
    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
