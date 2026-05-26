<?php

namespace App\Modules\Addons\Manual\Models;

use App\Models\BaseModel;

class ManualSection extends BaseModel
{
    protected $table = 'manual_sections';

    protected $fillable = [
        'module_slug',
        'title',
        'content',
        'version',
        'generated_at',
    ];

    protected $casts = [
        'version'      => 'integer',
        'generated_at' => 'datetime',
    ];
}
