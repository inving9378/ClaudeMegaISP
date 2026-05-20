<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParentalPlan extends BaseModel
{
    protected $table = 'parental_plans';

    protected $fillable = [
        'name', 'slug', 'price_monthly', 'max_children', 'max_devices',
        'max_parents', 'features', 'active',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'features' => 'array',
        'active' => 'boolean',
        'max_children' => 'integer',
        'max_devices' => 'integer',
        'max_parents' => 'integer',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(ParentalAccount::class, 'plan_id');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(ParentalLicense::class, 'plan_id');
    }
}
