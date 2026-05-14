<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalAppBlock extends BaseModel
{
    protected $table = 'parental_app_blocks';

    protected $fillable = [
        'profile_id', 'package_name', 'app_name', 'category',
        'blocked', 'schedule_start', 'schedule_end',
    ];

    protected $casts = ['blocked' => 'boolean'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
