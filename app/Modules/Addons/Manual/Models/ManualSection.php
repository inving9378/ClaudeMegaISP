<?php

namespace App\Modules\Addons\Manual\Models;

use App\Models\BaseModel;
use App\Support\Manual\HasManualRoleVisibility;

class ManualSection extends BaseModel
{
    use HasManualRoleVisibility;

    protected $table = 'manual_sections';

    protected $fillable = [
        'module_slug',
        'title',
        'content',
        'version',
        'generated_at',
        'visible_roles',
    ];

    protected $casts = [
        'version'       => 'integer',
        'generated_at'  => 'datetime',
        'visible_roles' => 'array',
    ];
}
