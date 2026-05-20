<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalWebBlock extends BaseModel
{
    protected $table = 'parental_web_blocks';

    protected $fillable = ['profile_id', 'domain', 'category', 'blocked'];

    protected $casts = ['blocked' => 'boolean'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
